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
use Symfony\Component\Console\Input\InputArgument;

use clsTinyButStrong as TinyButStrong;
use clsOpenTBS as OpenTBS;

class Main extends Command
{
  protected static $defaultName = 'main';

  /** @var TinyButStrong */
  private $tbs;

  public function __construct()
  {
    parent::__construct();

    $this->tbs = new TinyButStrong;
    if (class_exists(OpenTBS::class)) {
      $this->tbs->NoErr = true;
      $this->tbs->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    } else {
      throw new \RuntimeException('Unable to load OpenTBS backend.');
    }
  }


  protected function configure():void
  {
    $this->setDescription('Main entry point')
         ->setHelp('Cannot help you ...');

    $this->addArgument('template', InputArgument::REQUIRED, 'Office Document Template (LibreOffice, Word ...)');
    $this->addArgument('data', InputArgument::REQUIRED, 'Template Substitution Data in JSON format');
  }

  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    // outputs multiple lines to the console (adding "\n" at the end of each line)
    $output->writeln([
      'Hello World!',
      '... and so on ...',
    ]);

    // retrieve the argument value using getArgument()
    $templateFile = $input->getArgument('template');
    $templateDataFiles = [ $input->getArgument('data') ];
    if ($templateDataFiles[0] === basename($templateDataFiles[0], '.json')) {
      $templateDataFiles[] = $templateDataFiles[0] . '.json';
    }

    $output->writeln([
      'Template: ' . $templateFile,
      'Data: ' . implode(', ', $templateDataFiles),
    ]);

    $templateData = null;
    foreach ($templateDataFiles as $templateDataFile) {
      if (file_exists($templateDataFile)) {
        $templateData = file_get_contents($templateDataFile);
        break;
      }
    }
    if (empty($templateData)) {
      throw new \RuntimeException('Unable to read substitution data from (any of) the file(s) ' . implode(', ', $templateDataFiles));
    }

    $templateData = json_decode($templateData, true, JSON_BIGINT_AS_STRING);
    $output->writeln(print_r($templateData, true));

    $this->tbs->ResetVarRef(false);
    $this->tbs->VarRef = $templateData;

    $this->tbs->LoadTemplate($templateFile, OPENTBS_ALREADY_UTF8);

    $templatePathInfo = pathinfo($templateFile);
    // $output->writeln(print_r($templatePathInfo, true));

    $outputFileName = '/tmp/'
      . $templatePathInfo['filename'] . '-opentbs'
      . '.' . $templatePathInfo['extension'];

    $this->tbs->show(OPENTBS_FILE, $outputFileName);

    return Command::SUCCESS;
  }

}
