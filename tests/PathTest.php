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

use fkooman\RemoteStorage\Exception\PathException;
use PHPUnit_Framework_TestCase;

class PathTest extends PHPUnit_Framework_TestCase
{
    public function testPrivateDocument()
    {
        $p = new Path('/admin/path/to/Document.txt');
        $this->assertEquals('admin', $p->getUserId());
        $this->assertFalse($p->getIsPublic());
        $this->assertFalse($p->getIsFolder());
        $this->assertEquals('/admin/path/to/Document.txt', $p->getPath());
    }

    public function testPrivateFolder()
    {
        $p = new Path('/admin/path/to/Folder/');
        $this->assertEquals('admin', $p->getUserId());
        $this->assertFalse($p->getIsPublic());
        $this->assertTrue($p->getIsFolder());
        $this->assertEquals('/admin/path/to/Folder/', $p->getPath());
        $this->assertEquals('path', $p->getModuleName());
    }

    public function testChars()
    {
        $p = new Path('/Fran%C3%A7ois/foo/');
        $this->assertEquals('/François/foo/', $p->getPath());
    }

    public function testModuleName()
    {
        $p = new Path('/admin/public/foo/bar/baz.txt');
        $this->assertEquals('foo', $p->getModuleName());
        $p = new Path('/admin/public/foo/');
        $this->assertEquals('foo', $p->getModuleName());
        $p = new Path('/admin/foo/bar/baz.txt');
        $this->assertEquals('foo', $p->getModuleName());
        $p = new Path('/admin/foo/');
        $this->assertEquals('foo', $p->getModuleName());
    }

    public function testNoModuleName()
    {
        $p = new Path('/admin/public/foo');
        $this->assertFalse($p->getModuleName());
        $p = new Path('/admin/public/');
        $this->assertFalse($p->getModuleName());
        $p = new Path('/admin/foo');
        $this->assertFalse($p->getModuleName());
        $p = new Path('/admin/');
        $this->assertFalse($p->getModuleName());
    }

    public function testPublicDocument()
    {
        $p = new Path('/admin/public/path/to/Document.txt');
        $this->assertEquals('admin', $p->getUserId());
        $this->assertTrue($p->getIsPublic());
        $this->assertFalse($p->getIsFolder());
    }

    public function testPublicFolder()
    {
        $p = new Path('/admin/public/path/to/Folder/');
        $this->assertEquals('admin', $p->getUserId());
        $this->assertTrue($p->getIsPublic());
        $this->assertTrue($p->getIsFolder());
        $this->assertFalse($p->getIsDocument());
        $this->assertEquals('path', $p->getModuleName());
    }

    public function testValidPaths()
    {
        $testPath = [
            '/admin/public/foo/',
            '/admin/foo/',
            '/admin/public/foo/bar.txt',
            '/admin/public/foo/bar/very/long/path/with/Document',
        ];
        foreach ($testPath as $t) {
            try {
                $p = new Path($t);
                $this->assertTrue(true);
            } catch (PathException $e) {
                $this->assertTrue(false);
            }
        }
    }

    /**
     * @expectedException \fkooman\RemoteStorage\Exception\PathException
     * @expectedExceptionMessage invalid path
     */
    public function testNonStringPath()
    {
        $p = new Path(123);
    }

    public function testInvalidPaths()
    {
        $testPath = [
            '/',
            '/admin',
            '///',
            '/admin/foo//bar/',
            'admin/public/foo.txt',
            '/admin/foo/../../',
            '/foo%2fbar',
            '/foo/%2e%2e/bar',
        ];
        foreach ($testPath as $t) {
            try {
                $p = new Path($t);
                $this->assertTrue(false, $t);
            } catch (PathException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testDocumentPathTreeFolderFromUserRoot()
    {
        $path = new Path('/admin/contacts/work/colleagues.vcf');
        $this->assertEquals(
            [
                '/admin/',
                '/admin/contacts/',
                '/admin/contacts/work/',
            ],
            $path->getFolderTreeFromUserRoot()
        );
    }

    public function testFolderPathTreeFolderFromUserRoot()
    {
        $path = new Path('/admin/contacts/work/');
        $this->assertEquals(
            [
                '/admin/',
                '/admin/contacts/',
                '/admin/contacts/work/',
            ],
            $path->getFolderTreeFromUserRoot()
        );
    }

    public function testShortPathTreeFromUserRoot()
    {
        $path = new Path('/foo/bar');
        $this->assertEquals(
            [
                '/foo/',
            ],
            $path->getFolderTreeFromUserRoot()
        );
    }
}
