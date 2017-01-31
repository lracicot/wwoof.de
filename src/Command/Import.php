<?php

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Symfony\Component\DomCrawler\Crawler;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class Import extends Command
{
    private $container;

    public function __construct($container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
        // the name of the command (the part after "bin/console")
        ->setName('app:import')

        // the short description shown while running "php bin/console list"
        ->setDescription('Import all the data.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp("This command allows you to import all the data from wwoof.de");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client(['base_uri' => 'https://www.wwoof.de/']);

        $this->container['db']->executeUpdate('DELETE FROM farms');

        // * * * * * * * * * * * *
        // 1. Retreive the data from the source for an entity
        // * * * * * * * * * * * *

        //Get the farms
        $promise = $client->requestAsync('GET', '/index.php?article_id=116&clang=1&ajax=1&bl_id=&vk_id=&rauchen_id=&plz=Zoom%20in%20on%20City&kinderwunsch_id=&foej=0&sprach_id=25&monat_id=13&hof_id=');

        $rawData = [];
        $promise->then(
            function (ResponseInterface $res) use ($output, $client, &$rawData) {
                $output->writeln('Got the list. Try to convert to JSON');

                $bodyContents = $res->getBody()->getContents();

                $list = json_decode(str_replace("\'", '\"', $bodyContents));

                if ($list == null) {
                    throw new Exception('Unable to convert json response to object');
                }

                $output->writeln($list->count . ' places found');

                $farmPromises = [];
                $i=0;

                foreach ($list->places as $place) {
                    $farmId = (int)substr($place->title, 5);
                    $output->writeln('Getting data for farm #'.$farmId.' ('.++$i.'/'.$list->count.')');
                    $farmPromises[] = $client->getAsync('/index.php?article_id=4&clang=1&hof_id=' . $farmId)
                    ->then(function (ResponseInterface $farmRes) use ($place, $farmId, $output, &$rawData) {
                        $farmBody = $farmRes->getBody()->getContents();
                        $raw = new \stdClass();
                        $raw->originId = $farmId;
                        $raw->place = $place;
                        $raw->description = $farmBody;
                        $rawData[] = $raw;
                    });
                    usleep(1000000);
                }

                $results = Promise\unwrap($farmPromises);

                // Wait for the requests to complete, even if some of them fail
                $results = Promise\settle($farmPromises)->wait();
            },
            function (RequestException $e) {
                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
            }
        );
        $list = $promise->wait();

        // * * * * * * * * * * * *
        // 2. Transform the data
        // * * * * * * * * * * * *
        $transformedData = [];

        foreach ($rawData as $raw) {
            $output->writeln('tranforming '.$raw->originId);
            $transformed = $raw;

            $crawler = new Crawler($transformed->description);

            $crawler->filter('.navi-tab')->each(function (Crawler $crawler) use ($output) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                }
            });

            $crawler->filter('img')->each(function (Crawler $crawler) use ($output) {
                foreach ($crawler as $node) {
                    $node->setAttribute('src', 'https://www.wwoof.de'.$node->getAttribute('src'));
                }
            });


            $accepting = $crawler->filter('b')->count() == 0 || strpos($crawler->filter('b')->html(), 'Currently no WWOOF') === false;

            $crawler->filter('b')->each(function (Crawler $crawler) use ($output) {
                foreach ($crawler as $node) {
                    $node->parentNode->removeChild($node);
                    return;
                }
            });

            $output->writeln('transformed data for farm #'. $raw->originId);
            $transformed->acceptingRequest = $accepting;
            $transformed->description = $crawler->html();
            $transformedData[] = $transformed;
        }

        // * * * * * * * * * * * *
        // 3. Map the data in the destination format
        // * * * * * * * * * * * *
        $dataToInsert = [];
        foreach ($transformedData as $transformed) {
            $dataToInsert[] = [
                'wwoof_id' => $transformed->originId,
                'title' => substr($transformed->place->title, 0, strpos($transformed->place->title, '&')),
                'description' => $transformed->description,
                'accepting' => (int)($transformed->acceptingRequest),
            ];
        }

        // * * * * * * * * * * * *
        // 4. Insert the data in the destination
        // * * * * * * * * * * * *
        foreach ($dataToInsert as $toInsert) {
            $output->writeln('inserting data for farm #'.$toInsert['wwoof_id']);
            try {
                $this->container['db']->insert('farms', $toInsert);
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
            }
        }
    }
}
