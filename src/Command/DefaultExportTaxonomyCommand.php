<?php

/**
 * @file
 * Contains Drupal\Console\default_content\Command\DefaultExportTaxonomyCommand.
 */

namespace Drupal\Console\default_content\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Command\Shared\ModuleTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Symfony\Component\Filesystem\Filesystem;
use Drupal\Console\Extension\Manager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\default_content\Exporter;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * @DrupalCommand(
 *     extension = "default_content",
 *     extensionType = "module"
 * )
 */
class DefaultExportTaxonomyCommand extends Command {

    use CommandTrait;
    use ModuleTrait;

    /**
     * @var Manager
     */
    protected $extensionManager;

    /**
     * The entity manager.
     * @var EntityTypeManagerInterface
     */
    protected $entityManager;

    /**
     * @var StringConverter
     */
    protected $stringConverter;

    /**
     * @var QueryFactory
     */
    protected $entityQuery;

    /**
     * @var Exporter
     */
    protected $exporter;

    /**
     * DefaultExportMenuCommand constructor.
     * @param Manager $extensionManager
     * @param EntityTypeManagerInterface $entityManager
     * @param StringConverter $stringConverter
     * @param QueryFactory $entityQuery
     * @param Exporter $exporter
     */
    public function __construct(Manager $extensionManager, EntityTypeManagerInterface $entityManager, StringConverter $stringConverter, QueryFactory $entityQuery, Exporter $exporter)
    {
        $this->extensionManager = $extensionManager;
        $this->entityManager = $entityManager;
        $this->stringConverter = $stringConverter;
        $this->entityQuery =  $entityQuery;
        $this->exporter = $exporter;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this
            ->setName('default:content:export:taxonomy')
            ->setDescription($this->trans('commands.default.content.export.taxonony.description'))
            ->addArgument(
                'taxonomy-vid',
                InputArgument::REQUIRED,
                $this->trans('commands.default.content.export.arguments.taxonony-vid')
            )->addOption(
                'module',
                '',
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.common.options.module')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $io = new DrupalStyle($input, $output);
        $fileSystem = new Filesystem();

        $taxonomyVid = $input->getArgument('taxonomy-vid');
        $module = $input->getOption('module');

        if(!$module) {
            $io->warning($this->trans('commands.default.content.export.messages.module-not-found'));
            return;
        }

        $vocabularyStorage = $this->entityManager->getStorage('taxonomy_vocabulary');
        $termStorage = $this->entityManager->getStorage('taxonomy_term');

        $vocabulary = $vocabularyStorage->load($taxonomyVid);

        if($vocabulary) {

            $query = \Drupal::entityQuery('taxonomy_term');
            $query->condition('vid', $taxonomyVid);
            $terms = $query->execute();

            foreach($terms as $term) {
                $termEntity = $termStorage->load($term);
                $export = $this->exporter->exportContent('taxonomy_term', $term);
                $entityFileName = $this->stringConverter->createMachineName($termEntity->getName()) . '.json';
                $entityExportFile = $this->extensionManager->getModule($module)->getPath() . '/content/taxonomy_term/' . $entityFileName;

                $fileSystem->dumpFile($entityExportFile, $export);
            }

            $io->info(
                sprintf(
                    $this->trans('commands.default.content.export.messages.taxonomy-exported'),
                    $taxonomyVid,
                    $module
                )
            );

        } else {
            $io->warning(
                sprintf(
                    $this->trans('commands.default.content.export.messages.invalid-taxonomy'),
                    $taxonomyVid
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        // --module option
        $module = $input->getOption('module');
        if (!$module) {
            // @see Drupal\Console\Command\Shared\ModuleTrait::moduleQuestion
            $module = $this->moduleQuestion($io);
        }
        $input->setOption('module', $module);

        $entityQuery = $this->entityQuery
            ->get('taxonomy_vocabulary');
        $vocabularies = $entityQuery->execute();

        $taxonomyVid = $input->getArgument('taxonomy-vid');
        if (!$taxonomyVid) {
            $taxonomyVid = $io->choiceNoList(
                $this->trans('commands.default.content.export.taxonomy.questions.taxonomy'),
                array_keys($vocabularies),
                ''
            );
            $input->setArgument('taxonomy-vid', $taxonomyVid);
        }

    }
}