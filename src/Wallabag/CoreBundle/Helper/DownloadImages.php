<?php

namespace Wallabag\CoreBundle\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeExtensionGuesser;
use Symfony\Component\Finder\Finder;

class DownloadImages
{
    const REGENERATE_PICTURES_QUALITY = 80;

    private $client;
    private $baseFolder;
    private $logger;
    private $mimeGuesser;
    private $wallabagUrl;

    public function __construct(Client $client, $baseFolder, $wallabagUrl, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->baseFolder = $baseFolder;
        $this->wallabagUrl = rtrim($wallabagUrl, '/');
        $this->logger = $logger;
        $this->mimeGuesser = new MimeTypeExtensionGuesser();

        $this->setFolder();
    }

    /**
     * Setup base folder where all images are going to be saved.
     */
    private function setFolder()
    {
        // if folder doesn't exist, attempt to create one and store the folder name in property $folder
        if (!file_exists($this->baseFolder)) {
            mkdir($this->baseFolder, 0755, true);
        }
    }

    /**
     * Process the html and extract image from it, save them to local and return the updated html.
     *
     * @param int    $entryId ID of the entry
     * @param string $html
     * @param string $url     Used as a base path for relative image and folder
     *
     * @return string
     */
    public function processHtml($entryId, $html, $url)
    {
        $crawler = new Crawler($html);
        $result = $crawler
            ->filterXpath('//img')
            ->extract(array('src'));

        $relativePath = $this->getRelativePath($entryId);

        // download and save the image to the folder
        foreach ($result as $image) {
            $imagePath = $this->processSingleImage($entryId, $image, $url, $relativePath);

            if (false === $imagePath) {
                continue;
            }

            $html = str_replace($image, $imagePath, $html);
        }

        return $html;
    }

    /**
     * Process a single image:
     *     - retrieve it
     *     - re-saved it (for security reason)
     *     - return the new local path.
     *
     * @param int    $entryId      ID of the entry
     * @param string $imagePath    Path to the image to retrieve
     * @param string $url          Url from where the image were found
     * @param string $relativePath Relative local path to saved the image
     *
     * @return string Relative url to access the image from the web
     */
    public function processSingleImage($entryId, $imagePath, $url, $relativePath = null)
    {
        if (null === $relativePath) {
            $relativePath = $this->getRelativePath($entryId);
        }

        $this->logger->debug('DownloadImages: working on image: '.$imagePath);

        $folderPath = $this->baseFolder.'/'.$relativePath;

        // build image path
        $absolutePath = $this->getAbsoluteLink($url, $imagePath);
        if (false === $absolutePath) {
            $this->logger->error('DownloadImages: Can not determine the absolute path for that image, skipping.');

            return false;
        }

        try {
            $res = $this->client->get($absolutePath);
        } catch (\Exception $e) {
            $this->logger->error('DownloadImages: Can not retrieve image, skipping.', ['exception' => $e]);

            return false;
        }

        $ext = $this->mimeGuesser->guess($res->getHeader('content-type'));
        $this->logger->debug('DownloadImages: Checking extension', ['ext' => $ext, 'header' => $res->getHeader('content-type')]);
        if (!in_array($ext, ['jpeg', 'jpg', 'gif', 'png'], true)) {
            $this->logger->error('DownloadImages: Processed image with not allowed extension. Skipping '.$imagePath);

            return false;
        }
        $hashImage = hash('crc32', $absolutePath);
        $localPath = $folderPath.'/'.$hashImage.'.'.$ext;

        try {
            $im = imagecreatefromstring($res->getBody());
        } catch (\Exception $e) {
            $im = false;
        }

        if (false === $im) {
            $this->logger->error('DownloadImages: Error while regenerating image', ['path' => $localPath]);

            return false;
        }

        switch ($ext) {
            case 'gif':
                imagegif($im, $localPath);
                $this->logger->debug('DownloadImages: Re-creating gif');
                break;
            case 'jpeg':
            case 'jpg':
                imagejpeg($im, $localPath, self::REGENERATE_PICTURES_QUALITY);
                $this->logger->debug('DownloadImages: Re-creating jpg');
                break;
            case 'png':
                imagealphablending($im, false);
                imagesavealpha($im, true);
                imagepng($im, $localPath, ceil(self::REGENERATE_PICTURES_QUALITY / 100 * 9));
                $this->logger->debug('DownloadImages: Re-creating png');
        }

        imagedestroy($im);

        return $this->wallabagUrl.'/assets/images/'.$relativePath.'/'.$hashImage.'.'.$ext;
    }

    /**
     * Remove all images for the given entry id.
     *
     * @param int $entryId ID of the entry
     */
    public function removeImages($entryId)
    {
        $relativePath = $this->getRelativePath($entryId);
        $folderPath = $this->baseFolder.'/'.$relativePath;

        $finder = new Finder();
        $finder
            ->files()
            ->ignoreDotFiles(true)
            ->in($folderPath);

        foreach ($finder as $file) {
            @unlink($file->getRealPath());
        }

        @rmdir($folderPath);
    }

    /**
     * Generate the folder where we are going to save images based on the entry url.
     *
     * @param int $entryId ID of the entry
     *
     * @return string
     */
    private function getRelativePath($entryId)
    {
        $hashId = hash('crc32', $entryId);
        $relativePath = $hashId[0].'/'.$hashId[1].'/'.$hashId;
        $folderPath = $this->baseFolder.'/'.$relativePath;

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0777, true);
        }

        $this->logger->debug('DownloadImages: Folder used for that Entry id', ['folder' => $folderPath, 'entryId' => $entryId]);

        return $relativePath;
    }

    /**
     * Make an $url absolute based on the $base.
     *
     * @see Graby->makeAbsoluteStr
     *
     * @param string $base Base url
     * @param string $url  Url to make it absolute
     *
     * @return false|string
     */
    private function getAbsoluteLink($base, $url)
    {
        if (preg_match('!^https?://!i', $url)) {
            // already absolute
            return $url;
        }

        $base = new \SimplePie_IRI($base);

        // remove '//' in URL path (causes URLs not to resolve properly)
        if (isset($base->ipath)) {
            $base->ipath = preg_replace('!//+!', '/', $base->ipath);
        }

        if ($absolute = \SimplePie_IRI::absolutize($base, $url)) {
            return $absolute->get_uri();
        }

        $this->logger->error('DownloadImages: Can not make an absolute link', ['base' => $base, 'url' => $url]);

        return false;
    }
}
