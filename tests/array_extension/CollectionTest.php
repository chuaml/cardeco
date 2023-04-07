<?php

namespace test\array_extension;

use PHPUnit\Framework\TestCase;
use require_core\ArrayExtension\LazyCollection;

class CollectionTest extends TestCase
{
   public function testAddAndGet()
   {
      $collection = new LazyCollection();
      $collection->add(1);
      $collection->add(2);
      $collection->add(3);

      $this->assertEquals(1, $collection[0]);
      $this->assertEquals(2, $collection[1]);
      $this->assertEquals(3, $collection[2]);
   }

   public function testAdd()
   {
      $collection = new LazyCollection();
      $collection->add(1);
      $collection->add(2);

      $this->assertEquals([1, 2], $collection->toArray());
   }

   public function testRemove()
   {
      $collection = new LazyCollection([1, 2, 3]);

      $result = $collection->remove(function ($value) {
         return $value === 2;
      });
      $this->assertTrue($result);
      $this->assertEquals([1, 3], $collection->toArray());

      $result = $collection->remove(function ($value) {
         return $value === 4;
      });
      $this->assertFalse($result);
      $this->assertEquals([1, 3], $collection->toArray());
   }

   public function testFirst()
   {
      $collection = new LazyCollection([1, 2, 3]);

      $result = $collection->first(fn ($value) => $value === 2);
      $this->assertEquals(2, $result);

      $result = $collection->first(fn ($value) => $value === 4);
      $this->assertNull($result);
   }

   public function testFilter()
   {
      $collection = function () {
         return new LazyCollection([1, 2, 3, 4, 5, 6]);
      };

      $result = $collection()->filter(function ($value) {
         return $value % 2 === 0;
      });
      $this->assertEquals([2, 4, 6], $result->toArray());

      $result = $collection()->filter(function ($value) {
         return $value % 2 === 0;
      });
      $this->assertEquals([2, 4, 6], $result->toArray());

      $result = $collection()->filter(function ($value) {
         return $value % 2 !== 0;
      });
      $this->assertEquals([1, 3, 5], $result->toArray());
   }

   public function testForEach()
   {
      $collection = function () {
         return new LazyCollection([1, 2, 3, 4, 5, 6]);
      };

      $result = $collection()->filter(function ($value) {
         return $value % 2 === 0;
      });
      foreach ($result as $v) {
         $this->assertTrue($v % 2 === 0);
      }
      $this->assertNotEmpty($result);

      $result = $collection()->filter(function ($value) {
         return $value % 2 !== 0;
      });
      foreach ($result as $v) {
         $this->assertTrue($v % 2 !== 0);
      }
      $this->assertNotEmpty($result);
   }

   public function testMap()
   {
      $collection = new LazyCollection([1, 2, 3]);

      $result = $collection->map(function ($value) {
         return  $value * 2;
      });
      $this->assertEquals([2, 4, 6], $result->toArray());
   }

   public function testReduce()
   {
      $collection = new LazyCollection([1, 2, 3]);

      $result = $collection->reduce(function ($carry, $item) {
         return $carry + $item;
      }, 0);
      $this->assertEquals(6, $result);

      $result = $collection->reduce(function ($carry, $item) {
         $carry[] = $item;
         return $carry;
      }, []);
      $this->assertEquals([1, 2, 3], $result);
   }

   public function testCountBy_callback()
   {
      $collection = new LazyCollection([1, 2, 3, 4, 5]);

      $result = $collection->countBy(function ($value) {
         return $value % 2 === 0;
      });
      $this->assertEquals(2, $result);

      $result = $collection->countBy(function ($value) {
         return $value > 10;
      });
      $this->assertEquals(0, $result);
   }

   public function testOffsetSetAndGet()
   {
      $collection = new LazyCollection();
      $collection[0] = 1;
      $collection[1] = 2;
      $collection[2] = 3;

      $this->assertEquals(1, $collection[0]);
      $this->assertEquals(2, $collection[1]);
      $this->assertEquals(3, $collection[2]);

      $collection[3] = 4;
      $collection->filter(function ($v) {
         return $v % 2 === 0;
      });
      $this->assertEquals(1, $collection[0]);
      $this->assertEquals(2, $collection[1]);
      $this->assertEquals(3, $collection[2]);
      $this->assertEquals(4, $collection[3]);
   }

   public function testOffsetExistsAndUnset()
   {
      $collection = new LazyCollection();
      $collection->add(1);
      $collection->add(2);
      $collection->add(3);

      $this->assertTrue(isset($collection[0]));
      $this->assertFalse(isset($collection[3]));

      unset($collection[1]);
      $this->assertEquals(1, $collection[0]);
      $this->assertEquals(null, $collection[1]);
      $this->assertEquals(3, $collection[2]);
   }

   public function testCount()
   {
      $collection = new LazyCollection();
      $this->assertCount(0, $collection);

      $collection->add(1);
      $this->assertCount(1, $collection);

      $collection->add(2);
      $collection->add(3);
      $this->assertCount(3, $collection);

      $collection[] = 4;
      $collection[] = 5;
      $this->assertTrue(isset($collection[3]));
      $this->assertTrue(isset($collection[4]));
      $this->assertCount(5, $collection);

      // removing
      unset($collection[4]);
      $this->assertTrue(isset($collection[4]) === false);
      $this->assertTrue(array_key_exists(4, $collection->toArray()) === false);
      $this->assertCount(4, $collection);

      unset($collection[0]);
      $this->assertCount(3, $collection);

      $collection[1] = null;
      $this->assertCount(3, $collection);
      unset($collection[1]);
      $this->assertCount(2, $collection);
   }

   public function testJsonSerialize()
   {
      $collection = new LazyCollection();
      $collection->add(123456789);
      $collection->add(223456890);
      $collection->add(323456789);

      $collection[2] = 0;
      $collection[3] = 423456789;

      $this->assertEquals("[123456789,223456890,0,423456789]", json_encode($collection));

      $collection->filter(function ($v) {
         return $v !== 0;
      });
      $this->assertEquals("[123456789,223456890,423456789]", json_encode($collection));

   }
}
