<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Post;
use App\Entity\Favorite;
use App\Form\ChangePasswordType;
use App\Form\ProfileEditType;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
    public function userRegistration(Request                     $request, SluggerInterface $slugger,
                                     UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $registration_form = $this->createForm(UserType::class, $user);
        $registration_form->handleRequest($request);

        $existingUserByEmail = $this->em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        $existingUserByUsername = $this->em->getRepository(User::class)->findOneBy(['username' => $user->getUsername()]);

        if ($existingUserByEmail) {
            $this->addFlash('error', 'El correo electrónico ya está registrado');
        } elseif ($existingUserByUsername) {
            $this->addFlash('error', 'El nombre de usuario ya está registrado');
        } else {
            if ($registration_form->isSubmitted() && $registration_form->isValid()) {
                $file = $registration_form->get('photo')->getData();

                if ($file) {
                    $mimeTypes = new MimeTypes();
                    $allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

                    try {
                        $fileMimeType = $mimeTypes->guessMimeType($file->getRealPath());

                        if (!in_array($fileMimeType, $allowedImageMimeTypes)) {
                            $this->addFlash('error', 'Solo se permiten archivos de imagen (JPEG, PNG, GIF).');

                            return $this->redirectToRoute('userRegistration'); // Redirecciona si hay un error
                        }

                        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFileName = $slugger->slug($originalFileName);
                        $newFileName = $safeFileName . '-' . uniqid() . '.' . $file->guessExtension();

                        try {
                            $file->move(
                                $this->getParameter('photos_directory'),
                                $newFileName
                            );

                            $user->setPhoto($newFileName);
                        } catch (FileException $e) {
                            $this->addFlash('error', 'Ha habido un problema con tu archivo');

                            return $this->redirectToRoute('userRegistration');
                        }
                    } catch (InvalidArgumentException  $e) {
                        $this->addFlash('error', 'No se pudo determinar el tipo archivo.');

                        return $this->redirectToRoute('userRegistration');
                    }
                }

                $plaintextPassword = $registration_form->get('password')->getData();
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plaintextPassword
                );
                $user->setPassword($hashedPassword);
                $user->setRoles(['ROLE_USER']);
                $this->em->persist($user);
                $this->em->flush();

                $this->addFlash('success', 'Registro exitoso');
            }
        }

        return $this->render('user/index.html.twig', [
            'registration_form' => $registration_form->createView(),
        ]);
    }

    #[Route('/user/profile', name: 'userProfile')]
    public function userProfile(): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $editForm = $this->createForm(ProfileEditType::class, $user);
        $passwordForm = $this->createForm(ChangePasswordType::class, $user);

        return $this->render('user/profile.html.twig', [
            'user'         => $user,
            'editForm'     => $editForm->createView(),
            'passwordForm' => $passwordForm->createView(),
        ]);
    }

    #[Route('/user/profile/changePassword', name: 'userChangePassword')]
    public function changePassword(Request             $request, UserPasswordHasherInterface $passwordHasher,
                                   AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $passwordForm = $this->createForm(ChangePasswordType::class, $user);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {

            $plaintextPassword = $passwordForm->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $plaintextPassword
            );

            $user->setPassword($hashedPassword);

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Contraseña actualizada con éxito');

            return $this->redirectToRoute('userProfile');
        }

        return $this->redirectToRoute('userProfile');
    }

    #[Route('/user/profile/edit', name: 'userProfileEdit')]
    public function editProfile(Request $request, SluggerInterface $slugger, SessionInterface $session): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $originalEmail = $user->getEmail();
        $originalUsername = $user->getUsername();
        $originalPhoto = $user->getPhoto();

        $editForm = $this->createForm(ProfileEditType::class, $user);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $file = $editForm->get('photo')->getData();

            // Antes de cambiar la foto, guardo la ruta de la foto actual
            $originalPhotoPath = $this->getParameter('photos_directory') . '/' . $originalPhoto;

            if ($editForm->get('removePhoto')->getData()) {

                $user->setPhoto(null);
            } elseif ($file) {

                $mimeTypes = new MimeTypes();
                $allowedImageMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

                try {
                    $fileMimeType = $mimeTypes->guessMimeType($file->getRealPath());

                    if (!in_array($fileMimeType, $allowedImageMimeTypes)) {
                        $this->addFlash('error', 'Solo se permiten archivos de imagen (JPEG, PNG, GIF).');
                    }

                    $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFileName = $slugger->slug($originalFileName);
                    $newFileName = $safeFileName . '-' . uniqid() . '.' . $file->guessExtension();

                    try {
                        $file->move(
                            $this->getParameter('photos_directory'),
                            $newFileName
                        );

                        $user->setPhoto($newFileName);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Ha habido un problema con tu archivo');
                    }
                } catch (InvalidArgumentException  $e) {
                    $this->addFlash('error', 'No se pudo determinar el tipo archivo.');
                }
            } else {
                $user->setPhoto($originalPhoto);
            }

            $existingUserByEmail = $this->em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

            if ($existingUserByEmail && $existingUserByEmail->getId() !== $user->getId()) {
                $this->addFlash('error', 'El correo electrónico ya está en uso por otro usuario.');
                $user->setEmail($originalEmail);
            }

            $existingUserByUsername = $this->em->getRepository(User::class)->findOneBy(['username' => $user->getUsername()]);

            if ($existingUserByEmail && $existingUserByUsername->getId() !== $user->getId()) {
                $this->addFlash('error', 'El nombre de usuario ya está en uso por otro usuario.');
                $user->setUsername($originalUsername);
            }

            if (!$session->getFlashBag()->has('error')) {
                // Antes de hacer flush en la base de datos, verifica y elimina la foto anterior si existe
                if (($file || $editForm->get('removePhoto')->getData())
                    && !empty($originalPhoto)
                    && file_exists($originalPhotoPath)) {
                    unlink($originalPhotoPath);
                }

                $this->em->persist($user);
                $this->em->flush();
                $this->addFlash('success', 'Perfil actualizado con éxito');
            }
        }

        return $this->redirectToRoute('userProfile');
    }

    #[Route('/search/users', name: 'userSearch')]
    public function searchUsers(Request $request): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $searchTerm = $request->query->get('q');
        $users = [];

        if ($searchTerm) {
            // Lógica para buscar usuarios según $searchTerm
            $users = $this->em->getRepository(User::class)->findByUsername($searchTerm);
        }

        return $this->render('user/search.html.twig', [
            'users'      => $users,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/user/details/{id}', name: 'userDetails')]
    public function userDetails($id, Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

        $userData = $this->em->getRepository(User::class)->findOneBy(['id' => $id]);

        $userPosts = $userData->getPosts();

        $userPostsPaginated = $paginator->paginate(
            $userPosts,
            $request->query->getInt('page', 1),
            5
        );

        // Configuración de caché para evitar que la página se almacene en caché
        $response = new Response();
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '');

        return $this->render('user/details.html.twig', [
            'userDetails' => $userData,
            'userPosts'   => $userPostsPaginated,
        ], $response);
    }

    #[Route('/user/posts', name: 'userPosts')]
    public function userPosts(Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

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

        // Verificar si el usuario está autenticado
        if (!$user) {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }

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

    #[Route('/user/deleteAccount', name: 'deleteAccount')]
    public function deleteAccount(Request $request, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $user = $this->getUser();

        if ($user) {
            // Desconecta al usuario antes de eliminarlo de la base de datos
            $tokenStorage->setToken(null);

            $this->em->remove($user);
            $this->em->flush();

            return $this->json(['success' => true, 'message' => 'Cuenta eliminada con éxito',]);
        } else {
            throw new AccessDeniedException('Acceso denegado, no estás autenticado');
        }
    }
}
