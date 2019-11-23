<?php

namespace Doctrine\Tests\Common\Annotations;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Tests\Common\Annotations\Fixtures\Annotation\Route;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Psr\SimpleCache\CacheInterface;

class CachedReaderTest extends AbstractReaderTest
{
    private $cache;

    public function testIgnoresStaleCache()
    {
        $cache = time() - 10;
        touch(__DIR__.'/Fixtures/Controller.php', $cache + 10);

        $this->doTestCacheStale(Fixtures\Controller::class, $cache);
    }

    /**
     * @group 62
     */
    public function testIgnoresStaleCacheWithParentClass()
    {
        $cache = time() - 10;
        touch(__DIR__.'/Fixtures/ControllerWithParentClass.php', $cache - 10);
        touch(__DIR__.'/Fixtures/AbstractController.php', $cache + 10);

        $this->doTestCacheStale(Fixtures\ControllerWithParentClass::class, $cache);
    }

    /**
     * @group 62
     */
    public function testIgnoresStaleCacheWithTraits()
    {
        $cache = time() - 10;
        touch(__DIR__.'/Fixtures/ControllerWithTrait.php', $cache - 10);
        touch(__DIR__.'/Fixtures/Traits/SecretRouteTrait.php', $cache + 10);

        $this->doTestCacheStale(Fixtures\ControllerWithTrait::class, $cache);
    }

    /**
     * @group 62
     */
    public function testIgnoresStaleCacheWithTraitsThatUseOtherTraits()
    {
        $cache = time() - 10;

        touch(__DIR__ . '/Fixtures/ClassThatUsesTraitThatUsesAnotherTrait.php', $cache - 10);
        touch(__DIR__ . '/Fixtures/Traits/EmptyTrait.php', $cache + 10);

        $this->doTestCacheStale(
            Fixtures\ClassThatUsesTraitThatUsesAnotherTrait::class,
            $cache
        );
    }

    /**
     * @group 62
     */
    public function testIgnoresStaleCacheWithInterfacesThatExtendOtherInterfaces()
    {
        $cache = time() - 10;

        touch(__DIR__ . '/Fixtures/InterfaceThatExtendsAnInterface.php', $cache - 10);
        touch(__DIR__ . '/Fixtures/EmptyInterface.php', $cache + 10);

        $this->doTestCacheStale(
            Fixtures\InterfaceThatExtendsAnInterface::class,
            $cache
        );
    }

    /**
     * @group 62
     * @group 105
     */
    public function testUsesFreshCacheWithTraitsThatUseOtherTraits()
    {
        $cacheTime = time();

        touch(__DIR__ . '/Fixtures/ClassThatUsesTraitThatUsesAnotherTrait.php', $cacheTime - 10);
        touch(__DIR__ . '/Fixtures/Traits/EmptyTrait.php', $cacheTime - 10);

        $this->doTestCacheFresh(
            'Doctrine\Tests\Common\Annotations\Fixtures\ClassThatUsesTraitThatUsesAnotherTrait',
            $cacheTime
        );
    }

    protected function doTestCacheStale($className, $lastCacheModification)
    {
        $cacheKey = strtr($className, '\\', '.');

        /* @var $cache CacheInterface|\PHPUnit_Framework_MockObject_MockObject */
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->will($this->returnValue([])) // Result was cached, but there was no annotation
        ;
        $cache
            ->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('[C]'.$cacheKey))
            ->will($this->returnValue($lastCacheModification))
        ;
        $cache
            ->expects($this->at(2))
            ->method('set')
            ->with($this->equalTo($cacheKey))
            ->willReturn(true)
        ;
        $cache
            ->expects($this->at(3))
            ->method('set')
            ->with($this->equalTo('[C]'.$cacheKey))
            ->willReturn(true)
        ;

        $reader = CachedReader::fromPsr16Cache(new AnnotationReader(), $cache, true);
        $route = new Route();
        $route->pattern = '/someprefix';

        self::assertEquals([$route], $reader->getClassAnnotations(new \ReflectionClass($className)));
    }

    protected function doTestCacheFresh($className, $lastCacheModification)
    {
        $cacheKey       = strtr($className, '\\', '.');
        $route          = new Route();
        $route->pattern = '/someprefix';

        /* @var $cache CacheInterface|\PHPUnit_Framework_MockObject_MockObject */
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->will($this->returnValue([$route])); // Result was cached, but there was an annotation;
        $cache
            ->expects($this->at(1))
            ->method('get')
            ->with($this->equalTo('[C]' . $cacheKey))
            ->will($this->returnValue($lastCacheModification));
        $cache->expects(self::never())->method('set');

        $reader = CachedReader::fromPsr16Cache(new AnnotationReader(), $cache, true);

        $this->assertEquals([$route], $reader->getClassAnnotations(new \ReflectionClass($className)));
    }

    protected function getReader()
    {
        $this->cache = new ArrayCachePool();
        return CachedReader::fromPsr16Cache(new AnnotationReader(), $this->cache);
    }
}
