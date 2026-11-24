<?php

namespace App\Form;

use App\Entity\SitesScoresAudit;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SitesScoresAuditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('targets', TextareaType::class, [
                'label' => 'Target URLs (one per line)',
                'attr' => ['rows' => 6, 'placeholder' => "http://127.0.0.1:8001/\nhttps://example.local/"],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 4000),
                ],
            ])
            ->add('prompt', TextareaType::class, [
                'label' => 'Prompt enregistré',
                'attr' => ['rows' => 8],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 8000),
                ],
            ]);

        if ($options['isAdmin']) {
            $builder->add('owner', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Owner (Client)',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SitesScoresAudit::class,
            'isAdmin' => false,
        ]);
        $resolver->setAllowedTypes('isAdmin', 'bool');
    }
}
