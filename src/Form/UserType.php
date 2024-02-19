<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Email(['message' => 'Por favor, ingresa un correo electrónico válido.']),
                    new NotBlank(['message' => 'El campo de correo electrónico no puede estar en blanco.']),
                ],
            ])
            ->add('username', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, ingresa un nombre de usuario.']),
                ],
            ])
            ->add('password', PasswordType::class)
            ->add('password_repeat', PasswordType::class, [
                // No mapea este campo a una propiedad del objeto
                'mapped'      => false,
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, repite la contraseña.']),
                ],
            ])
            ->add('photo', FileType::class, [
                'label'      => 'Foto',
                'required'   => false,
                'data_class' => null,
            ])
            ->add('description')
            ->add('submit', SubmitType::class, [
                'label' => 'Confirmar',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}