<?php

namespace App\Form;

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nombre De Usuario',
                'constraints' => [
                    new NotBlank(['message' => 'Por favor, ingresa un nombre de usuario']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correo Electrónico',
                'constraints' => [
                    new Email(['message' => 'Por favor, ingresa un correo electrónico válido.']),
                    new NotBlank(['message' => 'El campo de correo electrónico no puede estar en blanco']),
                ],
            ])
            ->add('photo', FileType::class, [
                'label'      => 'Cambiar Foto',
                'required'   => false,
                'data_class' => null,
            ])
            ->add('removePhoto', CheckboxType::class, [
                'label'    => 'Eliminar Foto De Perfil',
                'required' => false,
                // No se mapea en la entidad
                'mapped'   => false,
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Descripción',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
