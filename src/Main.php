<?php
/**
 * OpenTBSTool -- Command-line interface for OpenTBS
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
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

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use clsTinyButStrong as TinyButStrong;
use clsOpenTBS as OpenTBS;

/** Command line entry point. */
class Main extends Command
{
  /**
   * @var string
   *
   * If present in the JSON data it is an array which maps deep-nested blocks
   * to aliases.
   */
  const EXPLICIT_BLOCKS_KEY = '__blocks__';

  protected static $defaultName = 'main';

  /** @var TinyButStrong */
  private $tbs;

  /** CTOR */
  public function __construct()
  {
    parent::__construct();

    $this->tbs = new TinyButStrong;
    if (class_exists(OpenTBS::class)) {
      $this->tbs->NoErr = true;
      $this->tbs->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    } else {
      throw new RuntimeException('Unable to load OpenTBS backend.');
    }
  }

  /** {@inheritdoc} */
  protected function configure():void
  {
    $this->setDescription('Command-line interface to the OpenTBS office document template reaplacement library.')
         ->setHelp('This is a low level command-line tool which needs a source file, the
substitution data-set in JSON format. It then combines the two input files and
substitutes the value from the JSON file into the given office document.');

    $this->addArgument('template', InputArgument::REQUIRED, 'Office Document Template (LibreOffice, Word ...)');
    $this->addArgument('data', InputArgument::REQUIRED, 'Template Substitution Data in JSON format');
    $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file');
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    if ($output instanceof ConsoleOutputInterface) {
      $output = $output->getErrorOutput();
    }

    // retrieve the argument value using getArgument()
    $templateFile = $input->getArgument('template');
    $templateDataFiles = [ $input->getArgument('data') ];
    if ($templateDataFiles[0] === basename($templateDataFiles[0], '.json')) {
      $templateDataFiles[] = $templateDataFiles[0] . '.json';
    }
    $outputFileName = $input->getOption('output');

    $output->writeln([
      'Template: ' . $templateFile,
      'Data: ' . implode(', ', $templateDataFiles),
      'Output: ' . $outputFileName,
    ]);

    $templateData = null;
    foreach ($templateDataFiles as $templateDataFile) {
      if (file_exists($templateDataFile)) {
        $templateData = file_get_contents($templateDataFile);
        break;
      }
    }
    if (empty($templateData)) {
      throw new RuntimeException('Unable to read substitution data from (any of) the file(s) ' . implode(', ', $templateDataFiles));
    }

    $templateData = json_decode($templateData, true, 512, JSON_BIGINT_AS_STRING);
    ksort($templateData);
    //$output->writeln(print_r($templateData, true));

    if (empty($outputFileName)) {
      $templatePathInfo = pathinfo($templateFile);

      $outputFileName = implode('/', [
        $templatePathInfo['dirname'],
        $templatePathInfo['filename']
        . '-opentbs-'
        . md5(json_encode($templateData))
        . '.' . $templatePathInfo['extension']
      ]);
    }

    $explicitBlocks = $templateData[self::EXPLICIT_BLOCKS_KEY] ?? [];
    unset($templateData[self::EXPLICIT_BLOCKS_KEY]);

    self::arrayWalkRecursive($templateData, function(mixed &$value, string $key, string $path, int $depth) use ($output) {
      if (!is_array($value)) {
        if ($value === false) {
          $value = 0;
        } elseif ($value === true) {
          $value = 1;
        } elseif ($value === null) {
          $value = '';
        }
        return;
      }
      ksort($value);
      if (count($value) == 3 && isset($value['date']) && isset($value['timezone_type']) && isset($value['timezone'])) {
        // convert back to DateTimeImmutable
        $timeZone = new DateTimeZone($value['timezone']);
        $date = new DateTimeImmutable($value['date'], $timeZone);
        // OpenTBS does not support DateTimeInterface or time-zones, so
        // convert everything to a time-stamp and add the timezone-offset
        // to get correct dates and times.
        $stamp = $date->getTimestamp();
        $stamp += $date->getOffset();
        $output->writeln('REPLACE DATE BY TIMESTAMP ' . $path . ': ' . print_r($date, true) . ' -> ' . $stamp);
        $value = $stamp;
      }
    });
    unset($value);

    $this->tbs->ResetVarRef(false);
    $this->tbs->VarRef = $templateData;

    $this->tbs->LoadTemplate($templateFile, OPENTBS_ALREADY_UTF8);

    foreach ($templateData as $key => $value) {
      if (is_array($value)) {
        // assume merge-block if a data item is an array, wrap it in
        // to a numeric array if necessary in order to please TBS.
        if (array_keys($value) != range(0, count($value) - 1)) {
          $value = [ $value ];
        }
        // $output->writeln('Try merge ' . $key . ' -> ' . print_r($value, true));
        $this->tbs->MergeBlock($key, $value);
      }
    }

    foreach ($explicitBlocks as $key => $reference) {
      $indices = explode('.', $reference);
      $value = $this->tbs->VarRef;
      while (!empty($indices) && !empty($value)) {
        $index = array_shift($indices);
        $value = $value[$index] ?? null;
      }
      if (empty($value)) {
        throw new RuntimeException(vsprintf('Data for block "%s" using the path "%s" could not be found in the substitution data.', [ $key, $reference ]));
      }
      $keys = array_keys($value);
      if ($keys != array_filter($keys, 'is_int')) {
        $value = [ $value ];
      }
      // $output->writeln("Try merge-block for " . $key . ' -> ' . $reference . ' -> ' . print_r($value, true));
      $this->tbs->MergeBlock($key, $value);
    }

    if ($outputFileName === '-') {
      $this->tbs->show(OPENTBS_STRING);
      fwrite(STDOUT, $this->tbs->Source);
    } else {
      $this->tbs->show(OPENTBS_FILE, $outputFileName);
    }

    return Command::SUCCESS;
  }

  /**
   * @param array $array
   *
   * @param Closure $callback
   *
   * @param string $parentPath
   *
   * @param int $depth
   *
   * @return void
   */
  protected static function arrayWalkRecursive(array &$array, Closure $callback, string $parentPath = '', int $depth = 0):void
  {
    foreach ($array as $key => &$value) {
      $path = $parentPath . ':' . $key;
      $callback($value, $key, $path, $depth);
      // if $value still is an array, recurse
      if (is_array($value)) {
        self::arrayWalkRecursive($value, $callback, $path, $depth + 1);
      }
    }
  }
}
