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

use PHPUnit\Framework\TestCase;

class DocumentStorageTest extends TestCase
{
    private $document;
    private $tempFile;

    public function setUp()
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        mkdir($tempFile);
        $this->document = new DocumentStorage($tempFile);
        $this->tempFile = $tempFile;
    }

    public function testPutDocument()
    {
        $p = new Path('/foo/bar/baz');
        $d = 'Hello World!';

        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
            ],
            $this->document->putDocument($p, $d)
        );
    }

    public function testPutTwoDocuments()
    {
        $p1 = new Path('/admin/messages/foo/baz.txt');
        $p2 = new Path('/admin/messages/foo/bar.txt');
        $p3 = new Path('/admin/messages/foo/');
        $this->assertEquals(
            [
                '/admin/',
                '/admin/messages/',
                '/admin/messages/foo/',
            ],
            $this->document->putDocument($p1, 'Hello Baz!')
        );
        $this->assertEquals(
            [
                '/admin/',
                '/admin/messages/',
                '/admin/messages/foo/',
            ],
            $this->document->putDocument($p2, 'Hello Bar!')
        );
        $this->assertEquals(
            [
                '/admin/',
                '/admin/messages/',
                '/admin/messages/foo/',
            ],
            $this->document->putDocument($p2, 'Hello Updated Bar!')
        );
        $this->assertEquals(
            [
                'bar.txt' => [
                    'Content-Length' => 18,
                ],
                'baz.txt' => [
                    'Content-Length' => 10,
                ],
            ],
            $this->document->getFolder($p3)
        );
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage document path is already a folder
     */
    public function testPutDocumentOnFolder()
    {
        // first write this
        $p = new Path('/foo/bar/baz');
        $this->document->putDocument($p, 'Hello World');
        // now try to write to /foo/bar as a file, bar is already a folder
        $p = new Path('/foo/bar');
        $this->document->putDocument($p, 'Hello World');
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Http\Exception\HttpException
     * @expectedExceptionMessage file already exists in path preventing folder creation
     */
    public function testPutFolderOnDocument()
    {
        // first write this
        $p = new Path('/foo/bar/baz');
        $this->document->putDocument($p, 'Hello World');
        // now try to write to /foo/bar as a file, bar is already a folder
        $p = new Path('/foo/bar/baz/foo');
        $this->document->putDocument($p, 'Hello World');
    }

    public function testGetDocument()
    {
        $p = new Path('/foo/bar/baz');
        $d = 'Hello World!';

        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
            ],
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals($d, $this->document->getDocument($p));
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\DocumentStorageException
     */
    public function testGetMissingDocument()
    {
        $p = new Path('/foo/bar/baz/foo');
        $this->document->getDocument($p);
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\DocumentStorageException
     */
    public function testDeleteMissingDocument()
    {
        $p = new Path('/foo/bar/baz/foo');
        $this->document->deleteDocument($p);
    }

    public function testDeleteDocument()
    {
        $p = new Path('/foo/bar/baz');
        $d = 'Hello World!';

        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
            ],
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals(
            [
                '/foo/bar/baz',
                '/foo/bar/',
                '/foo/',
            ],
            $this->document->deleteDocument($p)
        );
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\DocumentStorageException
     */
    public function testDoubleDeleteDocument()
    {
        $p = new Path('/foo/bar/baz');
        $d = 'Hello World!';

        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
            ],
            $this->document->putDocument($p, $d)
        );
        $this->assertEquals(
            [
                '/foo/bar/baz',
                '/foo/bar/',
                '/foo/',
            ],
            $this->document->deleteDocument($p)
        );
        $this->document->deleteDocument($p);
    }

    public function testGetFolder()
    {
        $p = new Path('/foo/bar/baz/foo');
        $d = 'Hello World!';

        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
                '/foo/bar/baz/',
            ],
            $this->document->putDocument($p, $d)
        );

        $parentFolder = new Path('/foo/bar/baz/');
        $this->assertEquals(['foo' => ['Content-Length' => 12]], $this->document->getFolder($parentFolder));

        $parentFolder = new Path('/foo/bar/');
        $this->assertEquals(['baz/' => []], $this->document->getFolder($parentFolder));

        $parentFolder = new Path('/foo/');
        $this->assertEquals(['bar/' => []], $this->document->getFolder($parentFolder));
    }

    public function testGetEmptyFolder()
    {
        $p = new Path('/foo/bar/baz/');
        $this->assertEquals([], $this->document->getFolder($p));
    }

    public function testRecursiveFolderDelete()
    {
        $p = new Path('/foo/bar/baz/foobar/foobaz');
        $d = 'Hello World!';
        $this->assertEquals(
            [
                '/foo/',
                '/foo/bar/',
                '/foo/bar/baz/',
                '/foo/bar/baz/foobar/',
            ],
            $this->document->putDocument($p, $d)
        );

        // now delete the document, the /foo/bar directory should be empty
        $this->assertEquals(
            [
                '/foo/bar/baz/foobar/foobaz',
                '/foo/bar/baz/foobar/',
                '/foo/bar/baz/',
                '/foo/bar/',
                '/foo/',
            ],
            $this->document->deleteDocument($p)
        );
        $this->assertEquals([], $this->document->getFolder(new Path('/foo/bar/')));
    }
}
