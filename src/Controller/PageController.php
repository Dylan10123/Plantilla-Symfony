<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Image;
use Symfony\Component\HttpFoundation\Request;
use App\Form\ImageFormType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\Contact;
use App\Form\ContactFormType;

class PageController extends AbstractController
{
    use TargetPathTrait;
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('page/index.html.twig', []);
    }

    #[Route('/about', name: 'about')]
    public function about(SessionInterface $session, $firewall = 'main'): Response
    {
        $this->saveTargetPath($session, $firewall, $this->generateUrl('about'));
        $this->denyAccessUnlessGranted("ROLE_USER");

        // if ($this->getUser()) {
        return $this->render('page/about.html.twig', []);
        // } else {
        //     $session->set('returnTo', 'about');
        //     return $this->redirectToRoute('app_login');
        // }
    }

    #[Route('/services', name: 'services')]
    public function services(SessionInterface $session, $firewall = 'main'): Response
    {
        $this->saveTargetPath($session, $firewall, $this->generateUrl('services'));
        $this->denyAccessUnlessGranted("ROLE_USER");

        return $this->render('page/services.html.twig', []);
    }

    #[Route('/portfolio', name: 'portfolio')]
    public function portfolio(SessionInterface $session, ManagerRegistry $doctrine, $firewall = 'main'): Response
    {
        $this->saveTargetPath($session, $firewall, $this->generateUrl('portfolio'));
        $this->denyAccessUnlessGranted("ROLE_USER");

        $repositorio = $doctrine->getRepository(Image::class);
        $imagenes = $repositorio->findAll();


        return $this->render('page/portfolio.html.twig', ['images' => $imagenes]);
    }

    // #[Route('/contact', name: 'contact')]
    // public function contact(SessionInterface $session, $firewall = 'main'): Response
    // {
    //     $this->saveTargetPath($session, $firewall, $this->generateUrl('contact'));
    //     $this->denyAccessUnlessGranted("ROLE_USER");

    //     return $this->render('page/contact.html.twig', []);
    // }

    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contacto = $form->getData();    
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($contacto);
            $entityManager->flush();
            return $this->redirectToRoute('thankyou', []);
        }
        return $this->render('page/contact.html.twig', array(
            'form' => $form->createView()    
        ));
    }
    
    #[Route('/admin/images', name: 'add_images')]
    public function images(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted("ROLE_ADMIN");

        $image = new Image();
        $form = $this->createForm(ImageFormType::class, $image);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
        
                // Move the file to the directory where images are stored
                try {
        
                    $file->move(
                        $this->getParameter('images_directory'), $newFilename
                    );
                    $filesystem = new Filesystem();
                    $sourcePath = $this->getParameter('images_directory')  . '/'. $newFilename;
                    $destinationPath = 'images/' . $newFilename;

                    $filesystem->copy($sourcePath, $destinationPath); 
        
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }
                $image->setFile($newFilename);
            }
            $image = $form->getData();   
            $entityManager = $doctrine->getManager();    
            $entityManager->persist($image);
            $entityManager->flush();
            return $this->redirectToRoute('portfolio', []);
        }
        return $this->render('admin/images.html.twig', array(
                    'form' => $form->createView(),
                    'image' => $image  
                ));
    }

    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response
    {
         return $this->render('page/thankyou.html.twig');
    }
}
