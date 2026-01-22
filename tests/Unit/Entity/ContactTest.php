<?php
/**
 * Contact Entity Test
 * 
 * Unit tests for the Contact entity class.
 * 
 * @package DotProject\Tests\Unit\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use DotProject\Entity\Contact;

/**
 * @covers \DotProject\Entity\Contact
 */
class ContactTest extends TestCase
{
    /**
     * Test creating contact with attributes
     */
    public function testCanCreateContactWithAttributes(): void
    {
        $contact = new Contact([
            'contact_first_name' => 'John',
            'contact_last_name' => 'Doe',
            'contact_email' => 'john@example.com',
        ]);

        $this->assertSame('John', $contact->getFirstName());
        $this->assertSame('Doe', $contact->getLastName());
        $this->assertSame('john@example.com', $contact->getEmail());
    }

    /**
     * Test getFullName
     */
    public function testGetFullName(): void
    {
        $contact = new Contact([
            'contact_first_name' => 'John',
            'contact_last_name' => 'Doe',
        ]);

        $this->assertSame('John Doe', $contact->getFullName());
    }

    /**
     * Test getFullName with only first name
     */
    public function testGetFullNameWithOnlyFirstName(): void
    {
        $contact = new Contact([
            'contact_first_name' => 'John',
        ]);

        $this->assertSame('John', $contact->getFullName());
    }

    /**
     * Test setFirstName
     */
    public function testSetFirstName(): void
    {
        $contact = new Contact([]);
        $contact->setFirstName('Jane');

        $this->assertSame('Jane', $contact->getFirstName());
    }

    /**
     * Test setLastName
     */
    public function testSetLastName(): void
    {
        $contact = new Contact([]);
        $contact->setLastName('Smith');

        $this->assertSame('Smith', $contact->getLastName());
    }

    /**
     * Test setEmail
     */
    public function testSetEmail(): void
    {
        $contact = new Contact([]);
        $contact->setEmail('test@example.com');

        $this->assertSame('test@example.com', $contact->getEmail());
    }

    /**
     * Test getCompanyId
     */
    public function testGetCompanyId(): void
    {
        $contact = new Contact(['contact_company' => 5]);

        $this->assertSame(5, $contact->getCompanyId());
    }

    /**
     * Test getCompanyId returns null when not set
     */
    public function testGetCompanyIdReturnsNullWhenNotSet(): void
    {
        $contact = new Contact([]);

        $this->assertNull($contact->getCompanyId());
    }

    /**
     * Test getTable
     */
    public function testGetTableReturnsContactsTable(): void
    {
        $this->assertSame('contacts', Contact::getTable());
    }

    /**
     * Test getPrimaryKey
     */
    public function testGetPrimaryKeyReturnsContactId(): void
    {
        $this->assertSame('contact_id', Contact::getPrimaryKey());
    }
}
