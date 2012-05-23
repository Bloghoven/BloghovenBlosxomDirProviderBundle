<?php

namespace Bloghoven\Bundle\BlosxomDirProviderBundle\Entity;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableEntryInterface;
use Bloghoven\Bundle\BlosxomDirProviderBundle\ContentProvider\BlosxomDirContentProvider;

use Gaufrette\File;
use Gaufrette\Path;

/**
* 
*/
class Entry implements ImmutableEntryInterface
{
  protected $file;
  protected $content_provider;

  protected $data_dir;
  protected $data_dir_info;

  public function __construct(File $file, BlosxomDirContentProvider $content_provider)
  {
    $this->file = $file;
    $this->content_provider = $content_provider;
  }

  public function getPathname()
  {
    return $this->file->getKey();
  }

  public function getPath()
  {
    return pathinfo($this->file->getKey(), PATHINFO_DIRNAME);
  }

  protected function getParent()
  {
    $parent_path = $this->getPath();

    if ($parent_path)
    {
      return new Category($parent_path, $this->content_provider);
    }
    return null;
  }

  // ------------------------------------------------------------

  // Getting the permalink id is kind of expensive, so
  // we'll cache it.
  protected $permalink_id;

  // These two are extra expensive to get, so whenever we get
  // one of them, we'll make sure to preload the other.
  protected $title;
  protected $contents;

  public function getPermalinkId()
  {
    if ($this->permalink_id === null)
    {
      $path = $this->getPathname();

      $path_info = pathinfo($path);

      $base_name = $path_info['filename'];

      $this->permalink_id = "";

      if ($this->getPath() != "")
      {
        $this->permalink_id .= $this->getPath().'/';
      }
      $this->permalink_id .= $base_name;
    }

    return $this->permalink_id;
  }

  public function getTitle()
  {
    if ($this->title === null)
    {
      $this->loadTitleAndContent();
    }

    return $this->title;
  }

  public function getExcerpt()
  {
    return $this->getContent();
  }

  public function getContent()
  {
    if ($this->content === null)
    {
      $this->loadTitleAndContent();
    }

    return $this->content;
  }

  protected function loadTitleAndContent()
  {
    $content = $this->file->getContent();

    $matches = array();

    preg_match("/(.*?)\n(.*)/ms", $content, $matches);

    $this->title = trim($matches[1]);
    $this->content = trim($matches[2]);
  }

  public function getPostedAt()
  {
    return $this->file->getCreated();
  }

  public function getModifiedAt()
  {
    return $this->file->getCreated();
  }

  public function isDraft()
  {
    return false;
  }

  public function getCategories()
  {
    $parent = $this->getParent();

    if (!$parent)
    {
      return null;
    }
    return array($parent);
  }
}