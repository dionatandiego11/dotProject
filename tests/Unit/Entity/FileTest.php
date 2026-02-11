<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use App\Entity\FileEntity;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Entity/FileEntity.php';

/**
 * @covers \App\Entity\FileEntity
 */
class FileTest extends TestCase
{
    public function testFromArrayMapsCoreFields(): void
    {
        $file = FileEntity::fromArray([
            'file_id' => 15,
            'file_name' => 'document.pdf',
            'file_real_filename' => '15_abc.pdf',
            'file_project' => 3,
            'file_task' => 7,
            'file_size' => 2048,
            'file_type' => 'application/pdf',
            'file_version' => 2.5,
            'file_checkout' => '',
            'file_date' => '2026-02-11 10:00:00',
        ]);

        $this->assertSame(15, $file->getId());
        $this->assertSame('document.pdf', $file->getName());
        $this->assertSame('15_abc.pdf', $file->getRealFilename());
        $this->assertSame(3, $file->getProjectId());
        $this->assertSame(7, $file->getTaskId());
        $this->assertSame(2048, $file->getSize());
        $this->assertSame('application/pdf', $file->getType());
        $this->assertSame(2.5, $file->getVersion());
        $this->assertSame('2026-02-11 10:00:00', $file->getDate()?->format('Y-m-d H:i:s'));
    }

    public function testGetFormattedSizeForCommonUnits(): void
    {
        $bytes = FileEntity::fromArray(['file_size' => 500]);
        $kilobytes = FileEntity::fromArray(['file_size' => 2048]);
        $megabytes = FileEntity::fromArray(['file_size' => 1048576]);

        $this->assertSame('500 B', $bytes->getFormattedSize());
        $this->assertSame('2 KB', $kilobytes->getFormattedSize());
        $this->assertSame('1 MB', $megabytes->getFormattedSize());
    }

    public function testGetExtensionReturnsSuffixOrEmptyString(): void
    {
        $withExtension = FileEntity::fromArray(['file_name' => 'document.pdf']);
        $withoutExtension = FileEntity::fromArray(['file_name' => 'document']);

        $this->assertSame('pdf', $withExtension->getExtension());
        $this->assertSame('', $withoutExtension->getExtension());
    }

    public function testIsImageDependsOnMimeTypePrefix(): void
    {
        $image = FileEntity::fromArray(['file_type' => 'image/png']);
        $document = FileEntity::fromArray(['file_type' => 'application/pdf']);
        $emptyType = FileEntity::fromArray([]);

        $this->assertTrue($image->isImage());
        $this->assertFalse($document->isImage());
        $this->assertFalse($emptyType->isImage());
    }

    public function testIsCheckedOutUsesCheckoutFlag(): void
    {
        $checkedOut = FileEntity::fromArray(['file_checkout' => '1']);
        $notCheckedOut = FileEntity::fromArray(['file_checkout' => '']);

        $this->assertTrue($checkedOut->isCheckedOut());
        $this->assertFalse($notCheckedOut->isCheckedOut());
    }

    public function testBelongsToProjectTaskAndFolder(): void
    {
        $entity = FileEntity::fromArray([
            'file_project' => 20,
            'file_task' => 30,
            'file_folder' => 2,
        ]);
        $empty = FileEntity::fromArray([]);

        $this->assertTrue($entity->belongsToProject());
        $this->assertTrue($entity->belongsToTask());
        $this->assertTrue($entity->isInFolder());
        $this->assertFalse($empty->belongsToProject());
        $this->assertFalse($empty->belongsToTask());
        $this->assertFalse($empty->isInFolder());
    }

    public function testToArrayContainsDerivedAttributes(): void
    {
        $entity = FileEntity::fromArray([
            'file_id' => 9,
            'file_name' => 'photo.png',
            'file_real_filename' => '9_photo.png',
            'file_size' => 1024,
            'file_type' => 'image/png',
            'file_checkout' => '',
        ]);

        $array = $entity->toArray();

        $this->assertSame(9, $array['id']);
        $this->assertSame('photo.png', $array['name']);
        $this->assertSame('1 KB', $array['size_formatted']);
        $this->assertTrue($array['is_image']);
        $this->assertFalse($array['is_checked_out']);
        $this->assertSame('png', $array['extension']);
    }
}
