<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Entity\Favorite;
use App\Entity\Interaction;
use App\Form\PostType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class PostController extends AbstractController
{
    private $em;
    private $requestStack;

    public function __construct(EntityManagerInterface $em, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
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
    public function postDetails(Request            $request, Post $post, InteractionController $interactionController,
                                PaginatorInterface $paginator): Response
    {
        $isInFavorites = $this->isPostInFavorites($this->getUser(), $post);
        $interactionForm = $interactionController->comment($this->requestStack->getCurrentRequest(), $post->getId());

        // Redirige a la página de detalles del post después de comentar
        if ($interactionForm->isSubmitted() && $interactionForm->isValid()) {
            return $this->redirectToRoute('postDetails', ['id' => $post->getId()]);
        }

        $comments = $this->em->getRepository(Interaction::class)->findBy(
            ['post' => $post],
        );

        $pagination = $paginator->paginate(
        // Query a paginar
            $comments,
            // Número de página por defecto
            $request->query->getInt('page', 1),
            // Número de elementos por página
            8
        );

        return $this->render('post/post-details.html.twig', [
            'post'             => $post,
            'isInFavorites'    => $isInFavorites,
            'interaction_form' => $interactionForm->createView(),
            'comments'         => $pagination,
        ]);
    }

    #[Route('/post/delete/{id}', name: 'postDelete')]
    public function deletePost($id): JsonResponse
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        try {
            $post = $this->em->getRepository(Post::class)->find($id);

            if (!$post) {
                return new JsonResponse(['success' => false, 'message' => 'No se encontró el post con el id: ' . $id]);
            }

            $photoFileName = $post->getFile();

            $this->em->remove($post);
            $this->em->flush();

            // Elimino la foto del post
            if ($photoFileName) {
                $photoFilePath = $this->getParameter('files_directory') . '/' . $photoFileName;

                if (file_exists($photoFilePath)) {
                    unlink($photoFilePath);
                }
            }

            return new JsonResponse(['success' => true, 'message' => 'El post fue eliminado exitosamente']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Hubo un problema al intentar eliminar el post']);
        }
    }

    #[Route('/post/details/{id}/addFavorite', name: 'postAddFavorite')]
    public function addToFavorites($id): JsonResponse
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $post = $this->em->getRepository(Post::class)->find($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Post no encontrado']);
        }

        $isFavorite = $this->em->getRepository(Favorite::class)->findOneBy(['user' => $user, 'post' => $post]);

        if ($isFavorite) {
            return new JsonResponse(['success' => false, 'message' => 'Este post ya está en favoritos']);
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setPost($post);

        $this->em->persist($favorite);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Post agregado a favoritos']);
    }

    #[Route('/post/details/{id}/removeFavorite', name: 'postDelFavorite')]
    public function removeFavorite(Request $request, $id): JsonResponse
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $post = $this->em->getRepository(Post::class)->find($id);

        if (!$post) {
            return new JsonResponse(['success' => false, 'message' => 'Post no encontrado']);
        }

        $favoriteRepository = $this->em->getRepository(Favorite::class);
        $favorite = $favoriteRepository->findOneBy(['user' => $user, 'post' => $post]);

        if (!$favorite) {
            return new JsonResponse(['success' => false, 'message' => 'Este post no está en favoritos']);
        }

        $this->em->remove($favorite);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Post eliminado de favoritos']);
    }

    private function isPostInFavorites($user, $post)
    {
        $favoriteRepository = $this->em->getRepository(Favorite::class);
        $isInFavorites = $favoriteRepository->findOneBy(['user' => $user, 'post' => $post]);

        return $isInFavorites !== null;
    }
}

