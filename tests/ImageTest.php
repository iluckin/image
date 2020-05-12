<?php

namespace Turbo\Foundation\Tests;

use ImagickException;
use Turbo\Foundation\Image;
use PHPUnit\Framework\TestCase;

/**
 * Class ImageTest
 * @package Turbo\Foundation\Tests
 */
class ImageTest extends TestCase
{
    /**
     * @throws ImagickException
     */
    public function testThumb()
    {
        Image::open("http://t8.baidu.com/it/u=2247852322,986532796&fm=79&app=86&f=JPEG?w=1280&h=853")
            ->thumb()->save('nice.jpg');
    }
}