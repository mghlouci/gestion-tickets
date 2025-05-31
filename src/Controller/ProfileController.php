<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Form\ProfileForm;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(Request $request, EntityManagerInterface $em, Security $security, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $security->getUser();
        $form = $this->createForm(ProfileForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->get('passwordSection');
            $oldPassword = $data->get('oldPassword')->getData();
            $newPassword = $data->get('newPassword')->getData();
            $confirmPassword = $data->get('confirmPassword')->getData();

            if ($oldPassword || $newPassword || $confirmPassword) {
                if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                    $this->addFlash('danger', 'Ancien mot de passe incorrect.');
                } elseif ($newPassword !== $confirmPassword) {
                    $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    $this->addFlash('success', 'Mot de passe mis à jour.');
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
