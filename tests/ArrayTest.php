<?php

namespace Hungarian\Tests;

use Hungarian\Hungarian;
use PHPUnit\Framework\TestCase;

class ArrayTest extends TestCase
{
    private $matrix1 = [
        [1, 2, 3, 0, 1],
        [0, 3, 12, 1, 1],
        [3, 0, 1, 13, 1],
        [3, 1, 1, 12, 0],
        [3, 1, 1, 12, 0],
    ];

    private $matrix2 = [
        [0, 2, 0, 0, 1],
        [0, 3, 12, 1, 1],
        [3, 1, 1, 13, 1],
        [3, 1, 1, 12, 0],
        [3, 1, 1, 12, 0],
    ];

    private $matrix3 = [
        [-3, -3, -3, -3, -2, -2, -2, -2, -99, -99],
        [-3, -3, -3, -3, -5, -5, -5, -5, -2, -99],
        [-2, -2, -2, -2, -5, -5, -5, -5, -3, -99],
        [-2, -2, -2, -2, -5, -5, -5, -5, -99, -3],
        [-3, -3, -3, -3, -2, -2, -2, -2, -99, -5],
        [-4, -4, -4, -4, -3, -3, -3, -3, -1, -99],
        [-4, -4, -4, -4, -3, -3, -3, -3, -99, -1],
        [-4, -4, -4, -4, -1, -1, -1, -1, -99, -99],
        [-1, -1, -1, -1, -3, -3, -3, -3, -6, -99],
        [-3, -3, -3, -3, -1, -1, -1, -1, -99, -6]
    ];

    private $matrix4 = [
        [-2, -2, -2, -2, -5, -5, -5, -5, -3, -99],
        [-2, -2, -2, -2, -5, -5, -5, -5, -99, -3],
        [-2, -2, -2, -2, -3, -3, -3, -3, -99, -99],
        [-3, -3, -3, -3, -5, -5, -5, -5, -8, -2],
        [-2, -2, -2, -2, -3, -3, -3, -3, -99, -8],
        [-3, -3, -3, -3, -1, -1, -1, -1, -99, -4],
        [-1, -1, -1, -1, -3, -3, -3, -3, -99, -99],
        [-3, -3, -3, -3, -1, -1, -1, -1, -6, -99],
        [-3, -3, -3, -3, -1, -1, -1, -1, -99, -6],
        [-1, -1, -1, -1, -3, -3, -3, -3, -7, -99]
    ];

    private $matrix5 = [
        [-5, -5, -5, -5, -3, -3, -3, -3, -6, -2],
        [-2, -2, -2, -2, -3, -3, -3, -3, -99, -6],
        [-3, -3, -3, -3, -2, -2, -2, -2, -99, -99],
        [-2, -2, -2, -2, -3, -3, -3, -3, -11, -5],
        [-3, -3, -3, -3, -2, -2, -2, -2, -99, -11],
        [-3, -3, -3, -3, -4, -4, -4, -4, -1, -7],
        [-4, -4, -4, -4, -1, -1, -1, -1, -3, -99],
        [-3, -3, -3, -3, -4, -4, -4, -4, -9, -1],
        [-1, -1, -1, -1, -4, -4, -4, -4, -99, -9],
        [-4, -4, -4, -4, -1, -1, -1, -1, -10, -3]
    ];

    public function testMatrix1()
    {
        $hungarian = new Hungarian($this->matrix1);
        $result = $hungarian->solve();
        $this->assertEquals(1, $hungarian->totalCost($result));
    }

    public function testMatrix2()
    {
        $hungarian = new Hungarian($this->matrix2);
        $result = $hungarian->solve();
        $this->assertEquals(2, $hungarian->totalCost($result));
    }
    public function testMatrix3()
    {
        $hungarian = new Hungarian($this->matrix3);
        $result = $hungarian->solve();
        $this->assertEquals(-231, $hungarian->totalCost($result));
    }
    public function testMatrix4()
    {
        $hungarian = new Hungarian($this->matrix4);
        $result = $hungarian->solve();
        $this->assertEquals(-227, $hungarian->totalCost($result));
    }
    public function testMatrix5()
    {
        $hungarian = new Hungarian($this->matrix5);
        $result = $hungarian->solve();
        $this->assertEquals(-229, $hungarian->totalCost($result));
    }
}