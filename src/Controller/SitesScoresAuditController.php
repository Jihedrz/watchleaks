<?php

namespace App\Controller;

use App\Entity\SitesScoresAudit;
use App\Form\SitesScoresAuditType;
use App\Repository\SitesScoresAuditRepository;
use App\Service\DomainContext;
use App\Service\SitesScoresRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/sites-scores')]
class SitesScoresAuditController extends AbstractController
{
    public function __construct(
        private readonly SitesScoresAuditRepository $repository,
        private readonly DomainContext $domainContext,
        private readonly SitesScoresRunner $runner,
    ) {
    }

    #[Route('', name: 'app_sites_scores_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('sites_scores_audit/index.html.twig', [
            'audits' => $this->repository->findForUser(
                $this->domainContext->currentUser()?->getId(),
                $this->domainContext->isAdmin()
            ),
            'isAdmin' => $this->domainContext->isAdmin(),
        ]);
    }

    #[Route('/new', name: 'app_sites_scores_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $audit = new SitesScoresAudit();
        $audit->setPrompt($this->defaultPrompt());

        $form = $this->createForm(SitesScoresAuditType::class, $audit, [
            'isAdmin' => $this->domainContext->isAdmin(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $targets = null;
            try {
                $targets = $this->runner->validateTargets(
                    (string) $audit->getTargets(),
                    $this->domainContext->isAdmin()
                );
            } catch (Throwable $e) {
                $form->get('targets')->addError(new FormError($e->getMessage()));
            }

            if ($targets !== null && $form->isValid()) {
                $audit->setTargets(implode("\n", $targets));
                $audit->setPrompt($audit->getPrompt()."\n\nCibles :\n".implode("\n", $targets));
                $audit->setOwner($this->domainContext->isAdmin()
                    ? $form->get('owner')->getData()
                    : $this->domainContext->currentUser());
                $audit->setFinishedAt(new \DateTimeImmutable());

                try {
                    $result = $this->runner->run($targets);
                    $report = $result['report'];
                    $summary = $this->runner->summarize($report);
                    $audit->setScore($summary['score']);
                    $audit->setGrade($summary['grade']);
                    $audit->setReportJson(json_encode(
                        $report,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ));

                    $lines = [
                        '[OK] Cibles validees : '.count($targets),
                        '[RUN] '.$result['command'],
                        '[OK] Rapport recu : '.count($report).' resultat(s)',
                    ];
                    foreach ($report as $item) {
                        $lines[] = sprintf(
                            '[%s] %s -> %s/100 (%s)',
                            !empty($item['reachable']) ? 'OK' : 'KO',
                            $item['url'] ?? '?',
                            $item['score'] ?? 'N/A',
                            $item['grade'] ?? 'N/A'
                        );
                        foreach ($item['checks'] ?? [] as $check) {
                            $lines[] = '   - '.(($check['passed'] ?? false) ? 'PASS' : 'FAIL').' '.($check['name'] ?? '').' : '.($check['detail'] ?? '');
                        }
                    }
                    $lines[] = '[OK] Score moyen : '.($summary['score'] ?? 'N/A').'/100 - Grade '.$summary['grade'];
                    if ($result['error_output'] !== '') {
                        $lines[] = '[stderr] '.$result['error_output'];
                    }
                    $lines[] = '';
                    $lines[] = '--- Sortie Sites_Scores ---';
                    $lines[] = $result['output'];
                    $audit->setExecutionLog(implode("\n", $lines));

                    $this->addFlash('success', sprintf('Audit termine : %s / %s', $summary['score'] ?? 'N/A', $summary['grade']));
                } catch (Throwable $e) {
                    $message = mb_substr($e->getMessage(), 0, 1000);
                    $audit->setError($message);
                    $audit->setExecutionLog("[RUN] Cibles : ".implode(', ', $targets)."\n[KO] ".$message);
                    $this->addFlash('danger', 'Audit enregistre avec une erreur : '.$message);
                }

                $em->persist($audit);
                $em->flush();

                return $this->redirectToRoute('app_sites_scores_show', ['id' => $audit->getId()]);
            }
        }

        return $this->render('sites_scores_audit/new.html.twig', [
            'form' => $form->createView(),
            'isAdmin' => $this->domainContext->isAdmin(),
        ]);
    }

    #[Route('/{id}', name: 'app_sites_scores_show', methods: ['GET'])]
    public function show(SitesScoresAudit $audit): Response
    {
        $this->denyAuditAccess($audit);

        $report = [];
        if ($audit->getReportJson() !== null) {
            try {
                $decoded = json_decode($audit->getReportJson(), true, 512, JSON_THROW_ON_ERROR);
                $report = is_array($decoded) ? $decoded : [];
            } catch (Throwable) {
                $report = [];
            }
        }

        return $this->render('sites_scores_audit/show.html.twig', [
            'audit' => $audit,
            'report' => $report,
            'isAdmin' => $this->domainContext->isAdmin(),
        ]);
    }

    #[Route('/{id}', name: 'app_sites_scores_delete', methods: ['POST'])]
    public function delete(SitesScoresAudit $audit, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAuditAccess($audit);

        if (!$this->isCsrfTokenValid('delete'.$audit->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('app_sites_scores_index');
        }

        $em->remove($audit);
        $em->flush();
        $this->addFlash('success', 'Audit deleted.');

        return $this->redirectToRoute('app_sites_scores_index');
    }

    private function denyAuditAccess(SitesScoresAudit $audit): void
    {
        if ($this->domainContext->isAdmin()) {
            return;
        }
        if ($audit->getOwner() !== $this->domainContext->currentUser()) {
            throw $this->createAccessDeniedException('You cannot access this audit.');
        }
    }

    private function defaultPrompt(): string
    {
        return "Audite uniquement les cibles indiquees avec le projet Sites_Scores en mode passif.\n"
            ."- requetes GET normales uniquement, aucun payload, aucune exploitation\n"
            ."- verifie les en-tetes HTTP, les cookies Secure/HttpOnly/SameSite et la configuration TLS\n"
            ."- calcule le score /100 et le grade A-E, puis fournis le rapport JSON detaille\n"
            ."- ne teste aucun autre hote, ne modifie aucune donnee\n"
            ."- demande validation avant toute action active";
    }
}
