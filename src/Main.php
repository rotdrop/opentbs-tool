<?php
/**
 * OpenTBSTool -- Command-line interface for OpenTBS
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace RotDrop\OpenTBSTool;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Main extends Command
{
  protected static $defaultName = 'main';

  public function __construct()
  {
    parent::__construct();
  }


  protected function configure():void
  {
    $this->setDescription('Main entry point')
         ->setHelp('Cannot help you ...');
  }

  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    // outputs multiple lines to the console (adding "\n" at the end of each line)
    $output->writeln([
      'Hello World!',
      '... and so on ...',
    ]);

    return Command::SUCCESS;
  }

}
