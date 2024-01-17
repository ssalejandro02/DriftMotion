<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Form\PostType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /*#[Route('/post/{id}', name: 'app_post')]
    public function index($id): Response
    {
        $post = $this->em->getRepository(Post::class)->findOneBy(['id' => $id]);

        return $this->render('post/index.html.twig', [
            'post' => $post,
        ]);
    }*/

    #[Route('/', name: 'index')]
    public function index(Request $request, SluggerInterface $slugger, PaginatorInterface $paginator): Response
    {
        $post = new Post();
        $query = $this->em->getRepository(Post::class)->findAllPosts();

        $pagination = $paginator->paginate(
            // Query NOT result
            $query,
            // Request with page number
            $request->query->getInt('page', 1),
            // Limit per page
            5
        );

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $url = str_replace(' ', '-', $form->get('title')->getData());

            if ($file) {
                $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFileName = $slugger->slug($originalFileName);
                $newFileName = $safeFileName . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('files_directory'),
                        $newFileName
                    );
                } catch (FileException $e) {
                    throw new \Exception('Ups there is a problem with your file');
                }

                $post->setFile($newFileName);
            }

            $post->setUrl($url);

            // Currently authenticated user
            $user = $this->getUser();

            // Associate the post to the current user
            $post->setUser($user);

            $this->em->persist($post);
            $this->em->flush();

            return $this->redirectToRoute('index');
        }

        return $this->render('post/index.html.twig', [
            'form'  => $form->createView(),
            'posts' => $pagination,
        ]);
    }

    #[Route('/post/details/{id}', name: 'postDetails')]
    public function postDetails(Post $post)
    {
        return $this->render('post/post-details.html.twig', ['post' => $post]);
    }
}

