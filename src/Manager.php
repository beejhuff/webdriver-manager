<?php
namespace Peridot\WebDriverManager;

use Peridot\WebDriverManager\Binary\BinaryResolver;
use Peridot\WebDriverManager\Binary\BinaryResolverInterface;
use Peridot\WebDriverManager\Binary\ChromeDriver;
use Peridot\WebDriverManager\Binary\DriverInterface;
use Peridot\WebDriverManager\Binary\SeleniumStandalone;
use Peridot\WebDriverManager\Event\EventEmitterInterface;
use Peridot\WebDriverManager\Event\EventEmitterTrait;
use Peridot\WebDriverManager\Process\SeleniumProcessInterface;
use Peridot\WebDriverManager\Process\SeleniumProcess;
use RuntimeException;

class Manager implements EventEmitterInterface
{
    use EventEmitterTrait;

    /**
     * @var array
     */
    protected $binaries;

    /**
     * @var BinaryResolverInterface
     */
    protected $resolver;

    /**
     * @var SeleniumProcessInterface
     */
    protected $process;

    /**
     * @param BinaryResolverInterface $resolver
     */
    public function __construct(BinaryResolverInterface $resolver = null, SeleniumProcessInterface $process = null) {
        $this->resolver = $resolver;
        $this->process = $process;

        $selenium = new SeleniumStandalone($this->getBinaryResolver());
        $chrome = new ChromeDriver($this->getBinaryResolver());

        $this->binaries = [
            $selenium->getName() => $selenium,
            $chrome->getName() => $chrome
        ];

        $this->inherit(['progress', 'request.start', 'complete'], $this->getBinaryResolver());
    }

    /**
     * Return the BinaryResolver used to resolve binary files.
     *
     * @return BinaryResolver|BinaryResolverInterface
     */
    public function getBinaryResolver()
    {
        if ($this->resolver === null) {
            $this->resolver = new BinaryResolver();
        }

        return $this->resolver;
    }

    /**
     * Return the SeleniumProcessInterface that will execute the
     * selenium server command.
     *
     * @return SeleniumProcess|SeleniumProcessInterface
     */
    public function getSeleniumProcess()
    {
        if ($this->process === null) {
            $this->process = new SeleniumProcess();
        }

        return $this->process;
    }

    /**
     * Return all managed binaries.
     *
     * @return array
     */
    public function getBinaries()
    {
        return $this->binaries;
    }

    /**
     * Return all binaries that are considered drivers.
     *
     * @return array
     */
    public function getDrivers()
    {
        $drivers = array_filter($this->binaries, function ($binary) {
            return $binary instanceof DriverInterface;
        });

        return array_values($drivers);
    }

    /**
     * Fetch and save binaries.
     *
     * @return bool
     */
    public function update($binaryName = '')
    {
        if ($binaryName) {
            $this->updateSingle($binaryName);
            return;
        }

        foreach ($this->binaries as $binary) {
            $binary->fetchAndSave($this->getInstallPath());
        }
    }

    /**
     * Update a single binary.
     *
     * @param $binaryName
     * @return void
     */
    public function updateSingle($binaryName)
    {
        if (! array_key_exists($binaryName, $this->binaries)) {
            throw new RuntimeException("Binary named $binaryName does not exist");
        }

        $binary = $this->binaries[$binaryName];
        $binary->fetchAndSave($this->getInstallPath());
    }

    /**
     * Start the Selenium server.
     *
     * @param int $port
     * @return SeleniumProcessInterface
     */
    public function start($port = 4444)
    {
        $selenium = $this->binaries['selenium'];
        $this->assertStartConditions($selenium);

        $process = $this->getSeleniumProcess();
        $process->addBinary($selenium, $this->getInstallPath());
        if ($port != 4444) {
            $process->addArg('-port', $port);
        }

        return $process->start();
    }

    /**
     * Remove all binaries from the install path.
     *
     * @return void
     */
    public function clean()
    {
        $files = glob($this->getInstallPath() . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get the installation path of binaries.
     *
     * @return string
     */
    public function getInstallPath()
    {
        return realpath(__DIR__ . '/../binaries');
    }

    /**
     * Assert that the selenium server can start.
     *
     * @param SeleniumStandalone $selenium
     */
    protected function assertStartConditions(SeleniumStandalone $selenium)
    {
        if (!$selenium->exists($this->getInstallPath())) {
            throw new RuntimeException("Selenium Standalone binary not installed");
        }

        if (!$this->getSeleniumProcess()->isAvailable()) {
            throw new RuntimeException('java is not available');
        }
    }
}
