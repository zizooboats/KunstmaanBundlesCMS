<?php

namespace Kunstmaan\PagePartBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Kunstmaan\AdminBundle\Modules\ClassLookup;

class PagePartRefRepository extends EntityRepository
{

    public function addPagePart($page, $pagepart, $sequencenumber, $context = "main"){
        $pagepartrefs = $this->getPagePartRefs($page);
        foreach($pagepartrefs as $pagepartref){
            if($pagepartref->getSequencenumber()>=$sequencenumber){
                $pagepartref->setSequencenumber($pagepartref->getSequencenumber()+1);
                $this->getEntityManager()->persist($pagepartref);
            }
        }
        $pagepartref = new \Kunstmaan\PagePartBundle\Entity\PagePartRef();
        $pagepartref->setContext($context);
        $page_classname = ClassLookup::getClass($page);
        $pagepartref->setPageEntityname($page_classname);
        $pagepartref->setPageId($page->getId());
        $pagepart_classname = ClassLookup::getClass($pagepart);
        $pagepartref->setPagePartEntityname($pagepart_classname);
        $pagepartref->setPagePartId($pagepart->getId());
        $pagepartref->setSequencenumber($sequencenumber);
        $this->getEntityManager()->persist($pagepartref);
        $this->getEntityManager()->flush();

        return $pagepartref;
    }

    public function getPagePartRefs($page, $context = "main"){
        return $this->findBy(array('pageId' => $page->getId(), 'pageEntityname' => ClassLookup::getClass($page), 'context' => $context), array('sequencenumber' => 'ASC'));
    }

    public function getPageParts($page, $context = "main"){
        $pagepartrefs = $this->getPagePartRefs($page, $context);
        $result = array();
        foreach($pagepartrefs as $pagepartref){
            $result[] = $pagepartref->getPagePart($this->getEntityManager());
        }

        return $result;
    }

    public function copyPageParts($em, $frompage, $topage, $context = "main"){
        $frompageparts = $this->getPageParts($frompage, $context);
        $sequencenumber = 1;
        foreach ($frompageparts as $frompagepart){
            $toppagepart = clone $frompagepart;
            $toppagepart->setId(null);
            $em->persist($toppagepart);
            $em->flush();
            $this->addPagePart($topage, $toppagepart, $sequencenumber, $context);
            $sequencenumber++;
        }
    }

	public function countPagePartsOfType($page, $pagepart_classname, $context = 'main')
	{
		$em = $this->getEntityManager();
		$page_classname = ClassLookup::getClass($page);

		$sql = 'SELECT COUNT(pp.id) FROM KunstmaanPagePartBundle:PagePartRef pp
				 WHERE pp.pageEntityname = :pageEntityname
				   AND pp.pageId = :pageId
				   AND pp.pagePartEntityname = :pagePartEntityname
				   AND pp.context = :context';
		return $em->createQuery($sql)
			->setParameter('pageEntityname', $page_classname)
			->setParameter('pageId', $page->getId())
			->setParameter('pagePartEntityname', $pagepart_classname)
			->setParameter('context', $context)
			->getSingleScalarResult();
	}
}
