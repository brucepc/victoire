<?php

namespace Victoire\Bundle\PageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Template
 *
 * @ORM\Table("cms_page_template")
 * @ORM\Entity
 */
class Template extends Page
{
    const TYPE = 'template';

    /**
     * @var string
     *
     * @ORM\OneToMany(targetEntity="\Victoire\Bundle\PageBundle\Entity\Page", mappedBy="template")
     */
    protected $pages;

    /**
     * contruct
     **/
    public function __construct()
    {
        parent::__construct();
        $this->widgets = new ArrayCollection();
    }

    /**
     * Set page
     * @param string $pages
     *
     * @return Template
     */
    public function setPages($pages)
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * Get pages
     *
     * @return string
     */
    public function getPages()
    {
        return $this->pages;
    }
}
