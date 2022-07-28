<?php

namespace ec5\Libraries\DirectoryGenerator;

use Illuminate\Contracts\Filesystem\Filesystem;

trait DirectoryGenerator
{

    /**
     * @param Filesystem $disk
     * @return \Generator
     */
    public function directoryGenerator(Filesystem $disk) {
        foreach ($disk->directories() as $dir) {
            yield $dir;
        }
    }

    /**
     * @param Filesystem $disk
     * @param $directory
     * @return \Generator
     */
    public function fileGenerator(Filesystem $disk, $directory) {
        foreach ($disk->files($directory) as $file) {
            yield $file;
        }
    }
}
