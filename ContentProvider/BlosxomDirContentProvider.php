<?php

namespace Bloghoven\Bundle\BlosxomDirProviderBundle\ContentProvider;

use Bloghoven\Bundle\BlosxomDirProviderBundle\Entity\Entry;
use Bloghoven\Bundle\BlosxomDirProviderBundle\Entity\Category;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ContentProviderInterface;
use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableCategoryInterface;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;

use Gaufrette\Filesystem;
use Gaufrette\Path;
use Gaufrette\StreamWrapper;

class BlosxomDirContentProvider implements ContentProviderInterface
{
  protected $filesystem;
  protected $file_extension;
  protected $depth;

  public function __construct(Filesystem $filesystem, $file_extension = 'txt', $depth = 0)
  {
    $this->filesystem = $filesystem;
    $this->file_extension = $file_extension;
    $this->depth = (int)$depth;
  }

  public function getFilesystem()
  {
    return $this->filesystem;
  }

  protected function validatePermalinkId($permalink_id)
  {
    if (strpos($permalink_id, '..') !== false)
    {
      throw new \RuntimeException("Permalinks with double dots are not allowed with the current provider, and are always advised against.");
    }
  }

  public function getFile($file_key)
  {
    $file = $this->filesystem->get($file_key);
    $file->setCreated(\DateTime::createFromFormat('U', $this->filesystem->mtime($file_key)));
    return $file;
  }

  /* ------------------ ContentProviderInterface methods ---------------- */

  protected function getSortedEntryKeysInSubdir($subdir = null, $depth = 0)
  {
    $extension = $this->file_extension;

    $keys = array_filter($this->filesystem->keys(), function ($key) use ($extension, $depth, $subdir) {
      $normalized_key = Path::normalize($key);

      if ($subdir)
      {
        if (strpos($normalized_key, $subdir.'/') !== 0)
        {
          return false;
        }
      }

      $path_info = pathinfo($normalized_key);

      if ($path_info['extension'] != $extension)
      {
        return false;
      }

      if ($depth > 0)
      {
        $dir_components = explode('/', $path_info['dirname']);
        if (count($dir_components) != 1 || $dir_components[0] != '.')
        {
          if (count($dir_components) > $depth-1)
          {
            return false;
          }
        }
      }

      return true;
    });

    $filesystem = $this->filesystem;

    usort($keys, function ($a, $b) use ($filesystem) {
      return $filesystem->mtime($b) - $filesystem->mtime($a);
    });

    return $keys;
  }

  public function getHomeEntriesPager()
  {
    $entries = array();

    foreach ($this->getSortedEntryKeysInSubdir(null, $this->depth) as $key)
    {
      $entries[] = new Entry($this->getFile($key), $this);
    }

    return new Pagerfanta(new ArrayAdapter($entries));
  }

  public function getEntriesPagerForCategory(ImmutableCategoryInterface $category)
  {
    if (!($category instanceof Category))
    {
      throw new \LogicException("The Blosxom dir provider only supports categories from the same provider.");
    }

    $entries = array();

    foreach ($this->getSortedEntryKeysInSubdir($category->getPathname()) as $file)
    {
      $entries[] = new Entry($this->getFile($file), $this);
    }

    return new Pagerfanta(new ArrayAdapter($entries));
  }

  public function getCategoryPaths($subdir = null, $depth = 0)
  {
    $keys = array_map(function ($key) use ($subdir) {
      $normalized_key = Path::normalize($key);

      if ($subdir)
      {
        if (strpos($normalized_key, $subdir) !== 0)
        {
          return '.';
        }

        return pathinfo(substr($normalized_key, strlen($subdir)+1), PATHINFO_DIRNAME);
      }

      return pathinfo($normalized_key, PATHINFO_DIRNAME);
    }, $this->filesystem->keys());

    $keys = array_unique($keys);

    return array_filter($keys, function ($key) use ($depth)
    {
      if ($key == '.')
      {
        return false;
      }

      if ($depth > 0)
      {
        $dir_components = explode('/', $key);
        if (count($dir_components) > $depth)
        {
          return false;
        }
      }

      return true;
    });
  }

  public function getCategoryRoots()
  {
    $categories = array();

    foreach ($this->getCategoryPaths(null, 1) as $dir)
    {
      $categories[] = new Category($dir, $this);
    }

    return $categories;
  }

  public function getEntryWithPermalinkId($permalink_id)
  {
    $this->validatePermalinkId($permalink_id);

    $key = $permalink_id.'.'.$this->file_extension;

    if ($this->filesystem->has($key))
    {
      return new Entry($this->getFile($key), $this);
    }
    return null;
  }

  protected function isCategory($path)
  {
    foreach ($this->filesystem->keys() as $key)
    {
      $normalized_key = Path::normalize($key);

      if (strpos($normalized_key, $path.'/') === 0)
      {
        return true;
      }
    }

    return false;
  }

  public function getCategoryWithPermalinkId($permalink_id)
  {
    $this->validatePermalinkId($permalink_id);

    if ($this->isCategory($permalink_id))
    {
      return new Category($permalink_id, $this);
    }
    return null;
  }
}