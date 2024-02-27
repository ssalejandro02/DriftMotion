<?php

namespace App\Controller;

use App\Entity\Interaction;
use App\Form\InteractionType;
use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class InteractionController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/post/details/{post_id}/comment', name: 'comment')]
    public function comment(Request $request, $post_id): FormInterface
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $post = $this->em->getRepository(Post::class)->find($post_id);

        if (!$post) {
            throw $this->createNotFoundException('El post no existe');
        }

        $interaction = new Interaction();
        $interaction->setPost($post);
        $interaction->setUser($user);
        $interaction->setDate(new \DateTime());

        $form = $this->createForm(InteractionType::class, $interaction);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($interaction);
            $this->em->flush();
        }

        return $form;
    }

    #[Route('/post/details/{post_id}/comment/{id}/delete', name: 'deleteComment')]
    public function deleteComment(Interaction $comment)
    {
        $user = $this->getUser();
        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        // Elimina el comentario
        $this->em->remove($comment);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Comentario eliminado correctamente']);
    }
}