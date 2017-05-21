<?php

namespace Wallabag\CoreBundle\Helper;

use JMS\Serializer;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use PHPePub\Core\EPub;
use PHPePub\Core\Structure\OPF\DublinCore;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class doesn't have unit test BUT it's fully covered by a functional test with ExportControllerTest.
 */
class EntriesExport
{
    private $wallabagUrl;
    private $logoPath;
    private $title = '';
    private $entries = [];
    private $authors = ['wallabag'];
    private $language = '';
    private $footerTemplate = '<div style="text-align:center;">
        <p>Produced by wallabag with %EXPORT_METHOD%</p>
        <p>Please open <a href="https://github.com/wallabag/wallabag/issues">an issue</a> if you have trouble with the display of this E-Book on your device.</p>
        </div>';

    /**
     * @param string $wallabagUrl Wallabag instance url
     * @param string $logoPath    Path to the logo FROM THE BUNDLE SCOPE
     */
    public function __construct($wallabagUrl, $logoPath)
    {
        $this->wallabagUrl = $wallabagUrl;
        $this->logoPath = $logoPath;
    }

    /**
     * Define entries.
     *
     * @param array|Entry $entries An array of entries or one entry
     *
     * @return EntriesExport
     */
    public function setEntries($entries)
    {
        if (!is_array($entries)) {
            $this->language = $entries->getLanguage();
            $entries = [$entries];
        }

        $this->entries = $entries;

        return $this;
    }

    /**
     * Sets the category of which we want to get articles, or just one entry.
     *
     * @param string $method Method to get articles
     *
     * @return EntriesExport
     */
    public function updateTitle($method)
    {
        $this->title = $method.' articles';

        if ('entry' === $method) {
            $this->title = $this->entries[0]->getTitle();
        }

        return $this;
    }

    /**
     * Sets the output format.
     *
     * @param string $format
     *
     * @return Response
     */
    public function exportAs($format)
    {
        $functionName = 'produce'.ucfirst($format);
        if (method_exists($this, $functionName)) {
            return $this->$functionName();
        }

        throw new \InvalidArgumentException(sprintf('The format "%s" is not yet supported.', $format));
    }

    public function exportJsonData()
    {
        return $this->prepareSerializingContent('json');
    }

    /**
     * Use PHPePub to dump a .epub file.
     *
     * @return Response
     */
    private function produceEpub()
    {
        /*
         * Start and End of the book
         */
        $content_start =
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:epub=\"http://www.idpf.org/2007/ops\">\n"
            .'<head>'
            ."<meta http-equiv=\"Default-Style\" content=\"text/html; charset=utf-8\" />\n"
            ."<title>wallabag articles book</title>\n"
            ."</head>\n"
            ."<body>\n";

        $bookEnd = "</body>\n</html>\n";

        $book = new EPub(EPub::BOOK_VERSION_EPUB3);

        /*
         * Book metadata
         */

        $book->setTitle($this->title);
        // Could also be the ISBN number, prefered for published books, or a UUID.
        $book->setIdentifier($this->title, EPub::IDENTIFIER_URI);
        // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
        $book->setLanguage($this->language);
        $book->setDescription('Some articles saved on my wallabag');

        foreach ($this->authors as $author) {
            $book->setAuthor($author, $author);
        }

        // I hope this is a non existant address :)
        $book->setPublisher('wallabag', 'wallabag');
        // Strictly not needed as the book date defaults to time().
        $book->setDate(time());
        $book->setSourceURL($this->wallabagUrl);

        $book->addDublinCoreMetadata(DublinCore::CONTRIBUTOR, 'PHP');
        $book->addDublinCoreMetadata(DublinCore::CONTRIBUTOR, 'wallabag');

        /*
         * Front page
         */
        if (file_exists($this->logoPath)) {
            $book->setCoverImage('Cover.png', file_get_contents($this->logoPath), 'image/png');
        }

        $book->buildTOC();

        /*
         * Adding actual entries
         */

        // set tags as subjects
        foreach ($this->entries as $entry) {
            foreach ($entry->getTags() as $tag) {
                $book->setSubject($tag->getLabel());
            }

            // the reader in Kobo Devices doesn't likes special caracters
            // in filenames, we limit to A-z/0-9
            $filename = preg_replace('/[^A-Za-z0-9\-]/', '', $entry->getTitle());

            $chapter = $content_start.$entry->getContent().$bookEnd;
            $book->addChapter($entry->getTitle(), htmlspecialchars($filename).'.html', $chapter, true, EPub::EXTERNAL_REF_ADD);
        }

        $book->addChapter('Notices', 'Cover2.html', $content_start.$this->getExportInformation('PHPePub').$bookEnd);

        return Response::create(
            $book->getBook(),
            200,
            [
                'Content-Description' => 'File Transfer',
                'Content-type' => 'application/epub+zip',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.epub"',
                'Content-Transfer-Encoding' => 'binary',
            ]
        );
    }

    /**
     * Use PHPMobi to dump a .mobi file.
     *
     * @return Response
     */
    private function produceMobi()
    {
        $mobi = new \MOBI();
        $content = new \MOBIFile();

        /*
         * Book metadata
         */
        $content->set('title', $this->title);
        $content->set('author', implode($this->authors));
        $content->set('subject', $this->title);

        /*
         * Front page
         */
        $content->appendParagraph($this->getExportInformation('PHPMobi'));
        if (file_exists($this->logoPath)) {
            $content->appendImage(imagecreatefrompng($this->logoPath));
        }
        $content->appendPageBreak();

        /*
         * Adding actual entries
         */
        foreach ($this->entries as $entry) {
            $content->appendChapterTitle($entry->getTitle());
            $content->appendParagraph($entry->getContent());
            $content->appendPageBreak();
        }
        $mobi->setContentProvider($content);

        // the browser inside Kindle Devices doesn't likes special caracters either, we limit to A-z/0-9
        $this->title = preg_replace('/[^A-Za-z0-9\-]/', '', $this->title);

        return Response::create(
            $mobi->toString(),
            200,
            [
                'Accept-Ranges' => 'bytes',
                'Content-Description' => 'File Transfer',
                'Content-type' => 'application/x-mobipocket-ebook',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.mobi"',
                'Content-Transfer-Encoding' => 'binary',
            ]
        );
    }

    /**
     * Use TCPDF to dump a .pdf file.
     *
     * @return Response
     */
    private function producePdf()
    {
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        /*
         * Book metadata
         */
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('wallabag');
        $pdf->SetTitle($this->title);
        $pdf->SetSubject('Articles via wallabag');
        $pdf->SetKeywords('wallabag');

        /*
         * Front page
         */
        $pdf->AddPage();
        $intro = '<h1>'.$this->title.'</h1>'.$this->getExportInformation('tcpdf');

        $pdf->writeHTMLCell(0, 0, '', '', $intro, 0, 1, 0, true, '', true);

        /*
         * Adding actual entries
         */
        foreach ($this->entries as $entry) {
            foreach ($entry->getTags() as $tag) {
                $pdf->SetKeywords($tag->getLabel());
            }

            $pdf->AddPage();
            $html = '<h1>'.$entry->getTitle().'</h1>';
            $html .= $entry->getContent();

            $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        }

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        return Response::create(
            $pdf->Output('', 'S'),
            200,
            [
                'Content-Description' => 'File Transfer',
                'Content-type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.pdf"',
                'Content-Transfer-Encoding' => 'binary',
            ]
        );
    }

    /**
     * Inspired from CsvFileDumper.
     *
     * @return Response
     */
    private function produceCsv()
    {
        $delimiter = ';';
        $enclosure = '"';
        $handle = fopen('php://memory', 'rb+');

        fputcsv($handle, ['Title', 'URL', 'Content', 'Tags', 'MIME Type', 'Language', 'Creation date'], $delimiter, $enclosure);

        foreach ($this->entries as $entry) {
            fputcsv(
                $handle,
                [
                    $entry->getTitle(),
                    $entry->getURL(),
                    // remove new line to avoid crazy results
                    str_replace(["\r\n", "\r", "\n"], '', $entry->getContent()),
                    implode(', ', $entry->getTags()->toArray()),
                    $entry->getMimetype(),
                    $entry->getLanguage(),
                    $entry->getCreatedAt()->format('d/m/Y h:i:s'),
                ],
                $delimiter,
                $enclosure
            );
        }

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        return Response::create(
            $output,
            200,
            [
                'Content-type' => 'application/csv',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.csv"',
                'Content-Transfer-Encoding' => 'UTF-8',
            ]
        );
    }

    /**
     * Dump a JSON file.
     *
     * @return Response
     */
    private function produceJson()
    {
        return Response::create(
            $this->prepareSerializingContent('json'),
            200,
            [
                'Content-type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.json"',
                'Content-Transfer-Encoding' => 'UTF-8',
            ]
        );
    }

    /**
     * Dump a XML file.
     *
     * @return Response
     */
    private function produceXml()
    {
        return Response::create(
            $this->prepareSerializingContent('xml'),
            200,
            [
                'Content-type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.xml"',
                'Content-Transfer-Encoding' => 'UTF-8',
            ]
        );
    }

    /**
     * Dump a TXT file.
     *
     * @return Response
     */
    private function produceTxt()
    {
        $content = '';
        $bar = str_repeat('=', 100);
        foreach ($this->entries as $entry) {
            $content .= "\n\n".$bar."\n\n".$entry->getTitle()."\n\n".$bar."\n\n";
            $content .= trim(preg_replace('/\s+/S', ' ', strip_tags($entry->getContent())))."\n\n";
        }

        return Response::create(
            $content,
            200,
            [
                'Content-type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="'.$this->title.'.txt"',
                'Content-Transfer-Encoding' => 'UTF-8',
            ]
        );
    }

    /**
     * Return a Serializer object for producing processes that need it (JSON & XML).
     *
     * @param string $format
     *
     * @return Serializer
     */
    private function prepareSerializingContent($format)
    {
        $serializer = SerializerBuilder::create()->build();

        return $serializer->serialize(
            $this->entries,
            $format,
            SerializationContext::create()->setGroups(['entries_for_user'])
        );
    }

    /**
     * Return a kind of footer / information for the epub.
     *
     * @param string $type Generator of the export, can be: tdpdf, PHPePub, PHPMobi
     *
     * @return string
     */
    private function getExportInformation($type)
    {
        $info = str_replace('%EXPORT_METHOD%', $type, $this->footerTemplate);

        if ('tcpdf' === $type) {
            return str_replace('%IMAGE%', '<img src="'.$this->logoPath.'" />', $info);
        }

        return str_replace('%IMAGE%', '', $info);
    }
}
