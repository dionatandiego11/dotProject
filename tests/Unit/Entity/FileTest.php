<?php
/**
 * File Entity Test
 * 
 * Unit tests for the File entity class.
 * 
 * @package DotProject\Tests\Unit\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use DotProject\Entity\File;

/**
 * @covers \DotProject\Entity\File
 */
class FileTest extends TestCase
{
    /**
     * Test creating file with attributes
     */
    public function testCanCreateFileWithAttributes(): void
    {
        $file = new File([
            'file_name' => 'document.pdf',
            'file_size' => 1024,
            'file_type' => 'application/pdf',
        ]);

        $this->assertSame('document.pdf', $file->getName());
        $this->assertSame(1024, $file->getSize());
        $this->assertSame('application/pdf', $file->getMimeType());
    }

    /**
     * Test getHumanSize for bytes
     */
    public function testGetHumanSizeBytes(): void
    {
        $file = new File(['file_size' => 500]);

        $this->assertSame('500 B', $file->getHumanSize());
    }

    /**
     * Test getHumanSize for kilobytes
     */
    public function testGetHumanSizeKilobytes(): void
    {
        $file = new File(['file_size' => 2048]);

        $this->assertSame('2 KB', $file->getHumanSize());
    }

    /**
     * Test getHumanSize for megabytes
     */
    public function testGetHumanSizeMegabytes(): void
    {
        $file = new File(['file_size' => 1048576]); // 1 MB

        $this->assertSame('1 MB', $file->getHumanSize());
    }

    /**
     * Test getExtension
     */
    public function testGetExtension(): void
    {
        $file = new File(['file_name' => 'document.PDF']);

        $this->assertSame('pdf', $file->getExtension());
    }

    /**
     * Test getExtension with no extension
     */
    public function testGetExtensionWithNoExtension(): void
    {
        $file = new File(['file_name' => 'document']);

        $this->assertSame('', $file->getExtension());
    }

    /**
     * Test isImage for image files
     */
    public function testIsImageReturnsTrueForImages(): void
    {
        $jpg = new File(['file_name' => 'photo.jpg']);
        $png = new File(['file_name' => 'image.png']);
        $gif = new File(['file_name' => 'animation.gif']);

        $this->assertTrue($jpg->isImage());
        $this->assertTrue($png->isImage());
        $this->assertTrue($gif->isImage());
    }

    /**
     * Test isImage for non-image files
     */
    public function testIsImageReturnsFalseForNonImages(): void
    {
        $pdf = new File(['file_name' => 'document.pdf']);
        $doc = new File(['file_name' => 'file.docx']);

        $this->assertFalse($pdf->isImage());
        $this->assertFalse($doc->isImage());
    }

    /**
     * Test isDocument for document files
     */
    public function testIsDocumentReturnsTrueForDocuments(): void
    {
        $pdf = new File(['file_name' => 'report.pdf']);
        $doc = new File(['file_name' => 'letter.docx']);
        $xls = new File(['file_name' => 'data.xlsx']);

        $this->assertTrue($pdf->isDocument());
        $this->assertTrue($doc->isDocument());
        $this->assertTrue($xls->isDocument());
    }

    /**
     * Test isDocument for non-document files
     */
    public function testIsDocumentReturnsFalseForNonDocuments(): void
    {
        $jpg = new File(['file_name' => 'photo.jpg']);
        $mp4 = new File(['file_name' => 'video.mp4']);

        $this->assertFalse($jpg->isDocument());
        $this->assertFalse($mp4->isDocument());
    }

    /**
     * Test isCheckedOut
     */
    public function testIsCheckedOut(): void
    {
        $checkedOut = new File(['file_checkout' => 1]);
        $notCheckedOut = new File(['file_checkout' => 0]);
        $noValue = new File([]);

        $this->assertTrue($checkedOut->isCheckedOut());
        $this->assertFalse($notCheckedOut->isCheckedOut());
        $this->assertFalse($noValue->isCheckedOut());
    }

    /**
     * Test getVersion
     */
    public function testGetVersion(): void
    {
        $file = new File(['file_version' => 3]);

        $this->assertSame(3, $file->getVersion());
    }

    /**
     * Test getVersion defaults to 1
     */
    public function testGetVersionDefaultsToOne(): void
    {
        $file = new File([]);

        $this->assertSame(1, $file->getVersion());
    }

    /**
     * Test getProjectId
     */
    public function testGetProjectId(): void
    {
        $file = new File(['file_project' => 10]);

        $this->assertSame(10, $file->getProjectId());
    }

    /**
     * Test getTaskId
     */
    public function testGetTaskId(): void
    {
        $file = new File(['file_task' => 25]);

        $this->assertSame(25, $file->getTaskId());
    }

    /**
     * Test getTable
     */
    public function testGetTableReturnsFilesTable(): void
    {
        $this->assertSame('files', File::getTable());
    }

    /**
     * Test getPrimaryKey
     */
    public function testGetPrimaryKeyReturnsFileId(): void
    {
        $this->assertSame('file_id', File::getPrimaryKey());
    }
}
