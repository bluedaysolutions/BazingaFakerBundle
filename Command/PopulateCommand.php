<?php

/**
 * This file is part of the FakerBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Bazinga\Bundle\FakerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class PopulateCommand extends Command
{
    /**
     * Populators do not have a common interface, so there is no proper typehinting.
     *
     * @var \Faker\ORM\Propel\Populator|\Faker\ORM\Propel2\Populator|\Faker\ORM\Doctrine\Populator|\Faker\ORM\CakePHP\Populator|\Faker\ORM\Spot\Populator
     */
    private $fakerOrmPopulator;

    public function __construct($fakerOrmPopulator)
    {
        $this->fakerOrmPopulator = $fakerOrmPopulator;

        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Populates configured entities with random data')
            ->setHelp(<<<HELP
The <info>faker:populate</info> command populates configured entities with random data.

  <info>php app/console faker:populate</info>

HELP
            )
            ->setName('faker:populate');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!\method_exists($this->fakerOrmPopulator, 'execute')) {
            $output->writeln('<error>Populator must implement execute() method.</error>');
            return 1;
        }
        $insertedPks = $this->fakerOrmPopulator->execute();

        $output->writeln('');

        if (count($insertedPks) === 0) {
            $output->writeln('<error>No entities populated.</error>');
        } else {
            foreach ($insertedPks as $class => $pks) {
                $reflClass = new \ReflectionClass($class);
                $shortClassName = $reflClass->getShortName();
                $output->writeln(sprintf('Inserted <info>%s</info> new <info>%s</info> objects', count($pks), $shortClassName));
            }
        }

        return 0;
    }
}
