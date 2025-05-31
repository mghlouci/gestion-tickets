<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Form\TicketForm;
use App\Form\TicketStatusForm;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Commentaire;
use App\Form\CommentaireForm;


#[Route('/ticket')]
final class TicketController extends AbstractController
{
    #[Route(name: 'app_ticket_index', methods: ['GET'])]
    public function index(TicketRepository $ticketRepository): Response
    {
        return $this->render('ticket/index.html.twig', [
            'tickets' => $ticketRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ticket = new Ticket();
        $form = $this->createForm(TicketForm::class, $ticket);
        $form->handleRequest($request);
        $ticket->setStatut('nouveau');
        $ticket->setDateCreation(new \DateTime());
        $ticket->setRapporteur($this->getUser());

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ticket);
            $entityManager->flush();

            return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ticket/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ticket_show', methods: ['GET', 'POST'])]
    public function show(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $em,
        Security $security
    ): Response {
        $user = $security->getUser();
        $form = null;
        $commentForm = null;

        // Formulaire de mise à jour du statut (visible seulement pour le développeur)
        if ($user === $ticket->getDeveloppeur()) {
            $form = $this->createForm(TicketStatusForm::class, $ticket);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $statut = $ticket->getStatut();
    
                if (in_array($statut, ['resolu', 'ferme'])) {
                    $ticket->setDateEcheance(new \DateTime());
                }

                $em->flush();
                $this->addFlash('success', 'Ticket mis à jour.');
                return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
            }
        }

        // Formulaire de commentaire (visible pour rapporteur ou développeur)
        if ($user === $ticket->getRapporteur() || $user === $ticket->getDeveloppeur()) {
            $commentaire = new Commentaire();
            $commentaire->setAuteur($user);
            $commentaire->setTicket($ticket);
            $commentaire->setDateCreation(new \DateTime());

            $commentForm = $this->createForm(CommentaireForm::class, $commentaire);
            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $em->persist($commentaire);
                $em->flush();
                $this->addFlash('success', 'Commentaire ajouté.');
                return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
            }
        }

        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'form' => $form?->createView(),
            'comment_form' => $commentForm?->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TicketForm::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ticket_delete', methods: ['POST'])]
    public function delete(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ticket->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($ticket);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/assign', name: 'app_ticket_assign')]
    public function assign(Ticket $ticket, EntityManagerInterface $em, Security $security): Response
    {
        $user = $security->getUser();

        // Vérifie que l'utilisateur est bien connecté et a le rôle DEVELOPPEUR
        if (!$this->isGranted('ROLE_DEV')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à vous assigner ce ticket.');
        }

        // Assigner le développeur
        $ticket->setDeveloppeur($user);
        $em->flush();

        // Redirige vers la page du ticket
        return $this->redirectToRoute('app_ticket_show', ['id' => $ticket->getId()]);
    }
}
