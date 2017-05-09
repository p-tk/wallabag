<?php

namespace Wallabag\ApiBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Wallabag\ApiBundle\Entity\Client;
use Wallabag\ApiBundle\Form\Type\ClientType;

class DeveloperController extends Controller
{
    /**
     * List all clients and link to create a new one.
     *
     * @Route("/developer", name="developer")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $clients = $this->getDoctrine()->getRepository('WallabagApiBundle:Client')->findByUser($this->getUser()->getId());
        $globalClients = $this->getDoctrine()->getRepository('WallabagApiBundle:Client')->findByUser(null);

        return $this->render('@WallabagCore/themes/common/Developer/index.html.twig', [
            'clients' => $clients,
            'global_clients' => $globalClients,
        ]);
    }

    /**
     * Create a global client (an app) for all users
     *
     * @param Request $request
     *
     * @Route("/api/apps", name="create_app")
     * @Method("POST")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAppAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $clientName = $request->request->get('client_name');
        $redirectURIs = $request->request->get('redirect_uris');
        $logoURI = $request->request->get('logo_uri');
        $description = $request->request->get('description');
        $appURI = $request->request->get('app_uri');

        if (!$clientName) {
            return new JsonResponse([
                'error' => 'invalid_client_name',
                'error_description' => 'The client name cannot be empty',
            ], 400);
        }

        if (!$redirectURIs) {
            return new JsonResponse([
                'error' => 'invalid_redirect_uri',
                'error_description' => 'One or more redirect_uri values are invalid',
            ], 400);
        }

        $redirectURIs = (array) $redirectURIs;

        $client = new Client();

        $client->setName($clientName);

        $client->setDescription($description);

        $client->setRedirectUris($redirectURIs);

        $client->setImage($logoURI);
        $client->setAppUrl($appURI);

        $client->setAllowedGrantTypes(['token', 'refresh_token', 'authorization_code']);
        $em->persist($client);
        $em->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            $this->get('translator')->trans('flashes.developer.notice.client_created', ['%name%' => $client->getName()])
        );



        return new JsonResponse([
            'client_id' => $client->getPublicId(),
            'client_secret' => $client->getSecret(),
            'client_name' => $client->getName(),
            'redirect_uri' => $client->getRedirectUris(),
            'description' => $client->getDescription(),
            'logo_uri' => $client->getImage(),
            'app_uri' => $client->getAppUrl(),
        ], 201);
    }

    /**
     * Create a client (an app).
     *
     * @param Request $request
     *
     * @Route("/developer/client/create", name="developer_create_client")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createClientAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $client = new Client($this->getUser());
        $clientForm = $this->createForm(ClientType::class, $client);
        $clientForm->handleRequest($request);

        if ($clientForm->isSubmitted() && $clientForm->isValid()) {
            $client->setAllowedGrantTypes(['password', 'token', 'refresh_token', 'client_credentials']); // Password is depreciated
            $em->persist($client);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'notice',
                $this->get('translator')->trans('flashes.developer.notice.client_created', ['%name%' => $client->getName()])
            );

            return $this->render('@WallabagCore/themes/common/Developer/client_parameters.html.twig', [
                'client_id' => $client->getPublicId(),
                'client_secret' => $client->getSecret(),
                'client_name' => $client->getName(),
            ]);
        }

        return $this->render('@WallabagCore/themes/common/Developer/client.html.twig', [
            'form' => $clientForm->createView(),
        ]);
    }

    /**
     * Remove a client.
     *
     * @param Client $client
     *
     * @Route("/developer/client/delete/{id}", requirements={"id" = "\d+"}, name="developer_delete_client")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteClientAction(Client $client)
    {
        if (($client->getUser() !== null) && (null === $this->getUser() || $client->getUser()->getId() != $this->getUser()->getId())) {
            throw $this->createAccessDeniedException('You can not access this client.');
        }

        if ($client->getImage() && $file = $this->getParameter('wallabag_api.applications_icon_path') . '/' . $client->getImage()) {
            unlink($file);
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($client);
        $em->flush();

        $this->get('session')->getFlashBag()->add(
            'notice',
            $this->get('translator')->trans('flashes.developer.notice.client_deleted', ['%name%' => $client->getName()])
        );

        return $this->redirect($this->generateUrl('developer'));
    }

    /**
     * Display developer how to use an existing app.
     *
     * @Route("/developer/howto/first-app", name="developer_howto_firstapp")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function howtoFirstAppAction()
    {
        return $this->render('@WallabagCore/themes/common/Developer/howto_app.html.twig');
    }
}
