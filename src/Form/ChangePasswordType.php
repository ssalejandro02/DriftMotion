<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           /* ->add('oldPassword', PasswordType::class, [
                'label' => 'Contraseña Actual',
                'mapped'      => false,
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, escriba su contraseña']),
                ],
            ])*/
            ->add('password', PasswordType::class, [
                'label' => 'Contraseña Nueva',
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, escriba la nueva contraseña']),
                ],
            ])
            ->add('password_repeat', PasswordType::class, [
                'label' => 'Repita La Nueva Contraseña',
                // No mapea este campo a una propiedad del objeto
                'mapped'      => false,
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, repite la contraseña']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
