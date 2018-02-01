<?php

/**
 * Factory for LibGuides backends.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;

use VuFindSearch\Backend\LibGuides\Backend;
use VuFindSearch\Backend\LibGuides\Connector;
use VuFindSearch\Backend\LibGuides\QueryBuilder;
use VuFindSearch\Backend\LibGuides\Response\RecordCollectionFactory;

use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for LibGuides backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LibGuidesBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * LibGuides configuration
     *
     * @var \Zend\Config\Config
     */
    protected $libGuidesConfig;

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->serviceLocator = $sm;
        $configReader = $this->serviceLocator->get('VuFind\Config\PluginManager');
        $this->libGuidesConfig = $configReader->get('LibGuides');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the LibGuides backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $defaultSearch = isset($this->libGuidesConfig->General->defaultSearch)
            ? $this->libGuidesConfig->General->defaultSearch : null;
        $backend = new Backend(
            $connector, $this->createRecordCollectionFactory(), $defaultSearch
        );
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the LibGuides connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        // Load credentials:
        $iid = isset($this->libGuidesConfig->General->iid)
            ? $this->libGuidesConfig->General->iid : null;

        // Pick version:
        $ver = isset($this->libGuidesConfig->General->version)
            ? $this->libGuidesConfig->General->version : 1;

        // Build HTTP client:
        $client = $this->serviceLocator->get('VuFindHttp\HttpService')
            ->createClient();
        $timeout = isset($this->libGuidesConfig->General->timeout)
            ? $this->libGuidesConfig->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);
        $connector = new Connector($iid, $client, $ver);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Create the LibGuides query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        return $builder;
    }

    /**
     * Create the record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('LibGuides');
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}
