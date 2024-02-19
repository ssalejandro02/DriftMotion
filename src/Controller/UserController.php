<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Favorite;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private $em;

    /**
     * @param $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/registration', name: 'userRegistration')]
    public function userRegistration(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $registration_form = $this->createForm(UserType::class, $user);
        $registration_form->handleRequest($request);

        if ($registration_form->isSubmitted() && $registration_form->isValid()) {
            $plaintextPassword = $registration_form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $plaintextPassword
            );
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
            $this->em->flush();

            return $this->redirectToRoute('userRegistration');
        }

        return $this->render('user/index.html.twig', [
            'registration_form' => $registration_form->createView(),
        ]);
    }

    #[Route('/user/posts', name: 'userPosts')]
    public function userPosts(Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $repository = $this->em->getRepository(Post::class);

        $query = $repository->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            // Ordenar por fecha de creación descendente
            ->orderBy('p.creation_date', 'DESC')
            ->getQuery();

        $posts = $query->getResult();

        // Configuración de caché para evitar que la página se almacene en caché
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '');

        $postsPaginated = $paginator->paginate(
            $posts,
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('user/posts.html.twig', [
            'posts' => $postsPaginated,
        ], $response);
    }

    #[Route('/user/favorites', name: 'userFavorites')]
    public function userFavorites(Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        $favorites = $this->em->getRepository(Favorite::class)->findBy(['user' => $user]);

        // Configuración de caché para evitar que la página se almacene en caché
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '');

        $favoritesPaginated = $paginator->paginate(
            $favorites,
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('user/favorites.html.twig', [
            'favorites' => $favoritesPaginated,
        ], $response);
    }
}
