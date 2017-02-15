<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Exception\DocumentStorageException;
use PDO;
use PHPUnit_Framework_TestCase;

class RemoteStorageTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\RemoteStorage\RemoteStorage */
    private $r;

    private $tempFile;

    public function setUp()
    {
        $random = $this->getMockBuilder('fkooman\RemoteStorage\RandomInterface')->getMock();
        $random->method('get')->willReturn('abcd1234');

        $md = new MetadataStorage(
            new PDO('sqlite::memory:'),
            $random
        );
        $md->initDatabase();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $this->tempFile = $tempFile;
        $document = new DocumentStorage($tempFile);
        $this->r = new RemoteStorage($md, $document);
    }

    public function testPutDocument()
    {
        $p = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $this->assertEquals(sprintf('%s/admin/messages/foo/hello.txt', $this->tempFile), $this->r->getDocument($p));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p));
    }

    public function testPutMultipleDocuments()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $p2 = new Path('/admin/messages/foo/bar.txt');
        $p3 = new Path('/admin/messages/foo/');
        $p4 = new Path('/admin/messages/');
        $p5 = new Path('/admin/');
        $this->r->putDocument($p1, 'text/plain', 'Hello World!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Foo!');
        $this->assertEquals(sprintf('%s/admin/messages/foo/hello.txt', $this->tempFile), $this->r->getDocument($p1));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p1));
        $this->assertEquals(sprintf('%s/admin/messages/foo/bar.txt', $this->tempFile), $this->r->getDocument($p2));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p2));
        // all parent directories should have version 2 now
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p3));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p4));
        $this->assertRegexp('/2:[a-z0-9]+/i', $this->r->getVersion($p5));
    }

    public function testDeleteDocument()
    {
        $p = new Path('/admin/messages/foo/baz.txt');
        $this->r->putDocument($p, 'text/plain', 'Hello World!');
        $documentVersion = $this->r->getVersion($p);
        $this->r->deleteDocument($p, [$documentVersion]);
        $this->assertNull($this->r->getVersion($p));
        try {
            $this->r->getDocument($p);
            $this->assertTrue(false);
        } catch (DocumentStorageException $e) {
            $this->assertTrue(true);
        }
        // directory should also not be there anymore
        $p = new Path('/admin/messages/foo/');
        $this->assertNull($this->r->getVersion($p));
    }

    public function testDeleteMultipleDocuments()
    {
        $p1 = new Path('/admin/messages/foo/baz.txt');
        $p2 = new Path('/admin/messages/foo/bar.txt');
        $p3 = new Path('/admin/messages/foo/');
        $p4 = new Path('/admin/messages/');
        $p5 = new Path('/admin/');

        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->deleteDocument($p1);
        $this->assertNull($this->r->getVersion($p1));
        $this->assertRegexp('/1:[a-z0-9]+/i', $this->r->getVersion($p2));
        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p3));
        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p4));
        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p5));
    }

    public function testGetFolder()
    {
        $p1 = new Path('/admin/messages/foo/baz.txt');
        $p2 = new Path('/admin/messages/foo/bar.txt');
        $p3 = new Path('/admin/messages/foo/');
        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Updated Bar!');

        $folderData = json_decode($this->r->getFolder($p3), true);
        $this->assertEquals(2, count($folderData));
        $this->assertEquals(2, count($folderData['items']));
        $this->assertEquals('http://remotestorage.io/spec/folder-description', $folderData['@context']);
        $this->assertRegexp('/2:[a-z0-9]+/i', $folderData['items']['bar.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['bar.txt']['Content-Type']);
        $this->assertEquals(18, $folderData['items']['bar.txt']['Content-Length']);
        $this->assertRegexp('/1:[a-z0-9]+/i', $folderData['items']['baz.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['baz.txt']['Content-Type']);
        $this->assertEquals(10, $folderData['items']['baz.txt']['Content-Length']);
        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p3));
    }

    public function testGetFolderWithFolder()
    {
        $p1 = new Path('/admin/messages/foo/baz.txt');
        $p2 = new Path('/admin/messages/foo/foobar/bar.txt');
        $p3 = new Path('/admin/messages/foo/');
        $this->r->putDocument($p1, 'text/plain', 'Hello Baz!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Bar!');
        $this->r->putDocument($p2, 'text/plain', 'Hello Updated Bar!');

        $folderData = json_decode($this->r->getFolder($p3), true);
        $this->assertEquals(2, count($folderData));
        $this->assertEquals(2, count($folderData['items']));
        $this->assertEquals('http://remotestorage.io/spec/folder-description', $folderData['@context']);
        $this->assertRegexp('/2:[a-z0-9]+/i', $folderData['items']['foobar/']['ETag']);
        $this->assertRegexp('/1:[a-z0-9]+/i', $folderData['items']['baz.txt']['ETag']);
        $this->assertEquals('text/plain', $folderData['items']['baz.txt']['Content-Type']);
        $this->assertEquals(10, $folderData['items']['baz.txt']['Content-Length']);

        $this->assertRegexp('/3:[a-z0-9]+/i', $this->r->getVersion($p3));
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage version mismatch
     */
    public function testPutIfMatchNotExistingFile()
    {
        $p1 = new Path('/admin/messages/foo/helloz0r.txt');
        //$this->r->putDocument($p1, 'text/plain', 'Hello World');
        $this->r->putDocument($p1, 'text/plain', 'Hello World', ['incorrect version']);
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage version mismatch
     */
    public function testPutIfMatchPrecondition()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p1, 'text/plain', 'Hello World');
        $this->r->putDocument($p1, 'text/plain', 'Hello World', ['incorrect version']);
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage version mismatch
     */
    public function testDeleteIfMatchPrecondition()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p1, 'text/plain', 'Hello World');
        $this->r->deleteDocument($p1, ['incorrect version']);
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\RemoteStorageException
     * @expectedExceptionMessage folder not modified
     */
    public function testGetFolderIfMatch()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $p2 = new Path('/admin/messages/foo/');
        $this->r->putDocument($p1, 'text/plain', 'Hello World');
        $folderVersion = $this->r->getVersion($p2);
        $this->r->getFolder($p2, [$folderVersion]);
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\RemoteStorageException
     * @expectedExceptionMessage document not modified
     */
    public function testGetDocumentIfMatch()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p1, 'text/plain', 'Hello World');
        $documentVersion = $this->r->getVersion($p1);
        $this->r->getDocument($p1, [$documentVersion]);
    }

    public function testPutDocumentIfNoneMatchStarOkay()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p1, 'text/plain', 'Hello World', null, ['*']);
        $this->assertNotNull($this->r->getVersion($p1));
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage document already exists
     */
    public function testPutDocumentIfNoneMatchStarFail()
    {
        $p1 = new Path('/admin/messages/foo/hello.txt');
        $this->r->putDocument($p1, 'text/plain', 'Hello World', null, ['*']);
        // document already exists now, so we fail
        $this->r->putDocument($p1, 'text/plain', 'Hello World', null, ['*']);
    }
}
