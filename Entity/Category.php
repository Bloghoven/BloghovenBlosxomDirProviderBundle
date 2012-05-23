<?php

namespace Bloghoven\Bundle\BlosxomDirProviderBundle\Entity;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableCategoryInterface;

use Bloghoven\Bundle\BlosxomDirProviderBundle\ContentProvider\BlosxomDirContentProvider;

use Symfony\Component\Finder\Finder;

use Gaufrette\Path;

/**
* 
*/
class Category implements ImmutableCategoryInterface
{
  protected $path;
  protected $content_provider;

  public function __construct($path, BlosxomDirContentProvider $content_provider)
  {
    $this->path = Path::normalize($path);
    $this->content_provider = $content_provider;
  }

  public function getPath()
  {
    return pathinfo($this->path, PATHINFO_DIRNAME);
  }

  public function getPathname()
  {
    return $this->path;
  }

  public function getParent()
  {
    $parent_path = $this->getPath();

    if ($parent_path)
    {
      return new Category($parent_path, $this->content_provider);
    }
    return null;
  }

  public function getName()
  {
    return pathinfo($this->path, PATHINFO_BASENAME);
  }

  public function getPermalinkId()
  {
    return $this->path;
  }

  public function getChildren()
  {
    $categories = array();

    foreach ($this->content_provider->getCategoryPaths($this->getPathname(), 1) as $dir)
    {
      $categories[] = new Category($dir, $this->content_provider);
    }

    return $categories;
  }
}