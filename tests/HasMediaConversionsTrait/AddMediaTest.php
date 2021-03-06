<?php

namespace Spatie\MediaLibrary\Test\HasMediaConversionsTrait;

use Carbon\Carbon;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\Test\TestCase;
use Spatie\MediaLibrary\Test\TestModelWithConversion;

class AddMediaTest extends TestCase
{
    /** @test */
    public function it_can_add_an_file_to_the_default_collection()
    {
        $media = $this->testModelWithoutMediaConversions
            ->copyMedia($this->getTestFilesDirectory('test.jpg'))
            ->toMediaLibrary();

        $this->assertEquals('default', $media->collection_name);
    }

    /** @test */
    public function it_can_create_a_derived_version_of_an_image()
    {
        $media = $this->testModelWithConversion->addMedia($this->getTestJpg())->toMediaLibrary('images');

        $this->assertFileExists($this->getMediaDirectory($media->id.'/conversions/thumb.jpg'));
    }

    /** @test */
    public function it_will_not_create_a_derived_version_for_non_registered_collections()
    {
        $media = $this->testModelWithoutMediaConversions->addMedia($this->getTestJpg())->toMediaLibrary('downloads');

        $this->assertFileNotExists($this->getMediaDirectory($media->id.'/conversions/thumb.jpg'));
    }

    /** @test */
    public function it_will_create_a_derived_version_for_an_image_without_an_extension()
    {
        $media = $this->testModelWithConversion
            ->addMedia($this->getTestFilesDirectory('image'))
            ->toMediaLibrary('images');

        $this->assertFileExists($this->getMediaDirectory($media->id.'/conversions/thumb.jpg'));
    }

    /** @test */
    public function it_will_use_the_name_of_the_conversion_for_naming_the_converted_file()
    {
        $modelClass = new class() extends TestModelWithConversion {
            public function registerMediaConversions()
            {
                $this->addMediaConversion('my-conversion')
                    ->setManipulations(function (Manipulations $manipulations) {
                        $manipulations
                            ->removeManipulation('format');
                    })
                    ->nonQueued();
            }
        };

        $model = $modelClass::first();

        $media = $model
            ->addMedia($this->getTestFilesDirectory('test.png'))
            ->toMediaLibrary('images');

        $this->assertFileExists($this->getMediaDirectory($media->id.'/conversions/my-conversion.png'));
    }

    /** @test */
    public function it_can_create_a_derived_version_of_a_pdf_if_imagick_exists()
    {
        $media = $this->testModelWithConversion
            ->addMedia($this->getTestFilesDirectory('test.pdf'))
            ->toMediaLibrary('images');

        $thumbPath = $this->getMediaDirectory($media->id.'/conversions/thumb.jpg');

        class_exists('Imagick') ? $this->assertFileExists($thumbPath) : $this->assertFileNotExists($thumbPath);
    }

    /** @test */
    public function it_will_not_create_a_derived_version_if_manipulations_did_not_change()
    {
        Carbon::setTestNow();

        $media = $this->testModelWithConversion->addMedia($this->getTestJpg())->toMediaLibrary('images');

        $originalThumbCreatedAt = filemtime($this->getMediaDirectory($media->id.'/conversions/thumb.jpg'));

        Carbon::setTestNow(Carbon::now()->addMinute());

        $media->order_column = $media->order_column + 1;
        $media->save();

        $thumbsCreatedAt = filemtime($this->getMediaDirectory($media->id.'/conversions/thumb.jpg'));

        $this->assertEquals($originalThumbCreatedAt, $thumbsCreatedAt);
    }
}
