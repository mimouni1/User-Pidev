<?php

namespace App\Controller;

use App\Entity\CodePromo;
use App\Form\CodePromoType;
use App\Repository\CodePromoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTime; 
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/code/promo')]
class CodePromoController extends AbstractController
{
    #[Route('/usecode', name: 'app_use_code')]
    public function usePromoCode(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, CodePromoRepository $codePromoRepository, Security $security) : Response 
    {
        $form = $this->createForm(CodePromoType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingCode = $codePromoRepository->findOneByCode($form['code']->getData());
        
            if ($existingCode != null) {
                $currentUser = $security->getUser();
                if ($currentUser->getCodePromo() == null) {
                    if ($existingCode->getDateFin() >= new \DateTime()) {
                        $existingCode->addUser($currentUser);
                        $entityManager->persist($existingCode);
                        $entityManager->flush();
                        $session->getFlashBag()->add('success', 'Promo code used successfully.');
        
                        return $this->redirectToRoute('index', [], Response::HTTP_SEE_OTHER);
                    } else {
                        $session->getFlashBag()->add('failure', 'Promo code has expired.');
                    }
                } else {
                    $session->getFlashBag()->add('failure', 'You already used a promo code.');
                }
            } else {
                $session->getFlashBag()->add('failure', 'Invalid promo code.');
            }
        }
        

        return $this->render('code_promo/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/', name: 'app_code_promo_index', methods: ['GET'])]
    public function index(CodePromoRepository $codePromoRepository): Response
    {
        return $this->render('code_promo/index.html.twig', [
            'code_promos' => $codePromoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_code_promo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        //$currentUser = $security->getUser();
        $generatedCode = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        $codePromo = new CodePromo();
        //$codePromo->addUser($currentUser);
        $codePromo->setCode($generatedCode);
        
        $currentDateTime = new DateTime();
        $codePromo->setDateDebut($currentDateTime);
        
        $customDate = new DateTime();
        $customDate->modify('+10 minute');
        $codePromo->setDateFin($customDate);

        $entityManager->persist($codePromo);
        $entityManager->flush();

        return $this->redirectToRoute('app_code_promo_index', [], Response::HTTP_SEE_OTHER);
    }

    

    #[Route('/{id}', name: 'app_code_promo_show', methods: ['GET'])]
    public function show(CodePromo $codePromo): Response
    {
        return $this->render('code_promo/show.html.twig', [
            'code_promo' => $codePromo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_code_promo_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CodePromo $codePromo, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CodePromoType::class, $codePromo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_code_promo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('code_promo/edit.html.twig', [
            'code_promo' => $codePromo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_code_promo_delete', methods: ['POST'])]
    public function delete(Request $request, CodePromo $codePromo, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$codePromo->getId(), $request->request->get('_token'))) {
            $users = $codePromo->getUser();

        foreach ($users as $user) {
            $user->setCodePromo(null);
        }
            $entityManager->remove($codePromo);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_code_promo_index', [], Response::HTTP_SEE_OTHER);
    }
}
