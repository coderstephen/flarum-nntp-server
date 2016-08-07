<?php
namespace coderstephen\flarum\nntpServer;

use Yosymfony\Toml\Toml;

class Config
{
    private $config = [];

    public static function load(string $filename): Config
    {
        $config = new self();

        if (is_file($filename)) {
            $config->config = Toml::Parse($filename);
        }

        return $config;
    }

    public function getFlarumUri(): string
    {
        return $this->config['flarum']['uri'];
    }

    public function getCacheDuration(): int
    {
        if (isset($this->config['server']['cache-duration'])) {
            return $this->config['server']['cache-duration'];
        }

        return 600;
    }
}
