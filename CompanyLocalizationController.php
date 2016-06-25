<?php

namespace Websolutio\SomeBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Websolutio\SomeBundle\Entity\CompanyLocalization;
use Websolutio\SomeBundle\Form\CompanyLocalizationType\CompanyLocalizationType;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CompanyLocalizationController extends Controller
{

	/*
	 * A way to retrieve geographical coordinates for certain place based on address (city / town, street) from openstreetmaps.org
	 * Retrieving coordinates starts from line 44
	 */
     
    public function updateAction(Request $request, $id)
    {
	if (!$this->get('security.context')->isGranted('ROLE_COMPANYUSER')) {
          throw new AccessDeniedException();
        }
        
	// get loged in user Id	
	$userId = $this->get('security.context')->getToken()->getUser()->getId();
		
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery("SELECT c FROM WebsolutioSomeBundle:CompanyLocalization c WHERE c.id  = $id AND c.userid = $userId ");
        $entity = $query->getSingleResult();

        if (!$entity) {
            return $this->forward('WebsolutioSomeBundle:CompanyLocalization:index');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new CompanyLocalizationType(), $entity);
        $editForm->bind($request);

        if ($editForm->isValid()) {
			// retrieve form data
			$post = $editForm['post']->getData();
			$street = $editForm['street']->getData();
			$streetno = $editForm['streetno']->getData();
			$postcode = $editForm['postcode']->getData();
			// process forms data - place (city/town) 'post' and street 'street'
			// add '%20' do street / town instead of white space ' ' (str_replace) - helps nominatim read compound names (e.g. New York)
			$trimmedstreet = str_replace(' ', '%20', $street);
			$trimmedpost = str_replace(' ', '%20', $post);
			// send processed address to openstreetmap
			$url = 'http://nominatim.openstreetmap.org/search/en/'.$trimmedpost.'/'.$trimmedstreet.'?format=json';
			$content = file_get_contents($url);
			$json = json_decode($content, true);
			// retrieve geocoordinates from json response
     		$entity->setLatitiude( $json[0]['lat']);
			$entity->setLongtitiude($json[0]['lon']);
			// save latitude / longtitude to database
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('companylocalization_description', array('companylocalization' => $id)));
        }

        return $this->render('WebsolutioSomeBundle:CompanyLocalization:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }
}
