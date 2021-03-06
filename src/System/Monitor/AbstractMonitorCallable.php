<?php
declare(strict_types = 1);
namespace GPRS\System\Monitor;

use BCL\System\AbstractObject;
use BCL\System\Logger\LogManager;
use GPRS\System\Connection;
use GPRS\System\ModemManagerInterface;
use GPRS\System\Entities\ModemEntity;

/**
 * Contêm os métodos e propriedades basicas para um callable de processamento de etapas de monitoramento.
 *
 * @since 1.0
 * @version 1.0
 * @author Silas B. Domingos
 * @copyright Silas B. Domingos
 * @package GPRS\System\Monitor
 */
abstract class AbstractMonitorCallable extends AbstractObject
{

    /**
     * Instância da conexão.
     *
     * @var Connection
     */
    private $connection;

    /**
     * Instância do gerenciador de modems.
     *
     * @var ModemManagerInterface
     */
    protected $manager;

    /**
     * Instância com as informações do modem.
     *
     * @var ModemEntity
     */
    protected $modem;

    /**
     * Instância do gerenciador de registros.
     *
     * @var LogManager
     */
    protected $logger;

    /**
     * Executa a ação.
     *
     * @return bool
     */
    abstract protected function execute(): bool;

    /**
     * Verifica se o modem esta em espera.
     *
     * @return bool
     */
    private function modemWaiting(): bool
    {
        if (time() < (int) $this->modem->getData('monitor.waitTime')) {
            return true;
        } else
            if ((bool) $this->modem->getData('monitor.sleeping') === true) {

                $this->modem->setData('monitor.sleeping', false);
                $this->logger->logNotice('modem wake up');
            }

        return false;
    }

    /**
     * Verifica se a etapa atual do modem esta em espera.
     *
     * @return bool
     */
    private function stageWaiting(): bool
    {
        if (time() < (int) $this->modem->getStageData('monitor.waitTime')) {

            $this->modem->nextStage();
            return true;
        } else
            if ((bool) $this->modem->getStageData('monitor.sleeping') === true) {

                $this->modem->setStageData('monitor.sleeping', false);
                $this->logger->logNotice('stage wake up');
            }

        return false;
    }

    /**
     * Coloca o modem em espera.
     *
     * @param int $seconds
     *            Tempo em segundos.
     * @param bool $log
     *            Defina True para registrar o modo de espera ou False para não registrar.
     * @return void
     */
    protected function sleepModem(int $seconds, bool $log = true)
    {
        $waitTime = (time() + $seconds);
        $this->modem->setData('monitor.waitTime', $waitTime);

        if ($log) {

            $this->modem->setData('monitor.sleeping', true);
            $this->logger->logNotice('modem wait to: %s, %d seconds', date('Y/m/d H:i:s', $waitTime), $seconds);
        }
    }

    /**
     * Coloca a etapa atual do modem em espera.
     *
     * @param int $seconds
     *            Tempo em segundos.
     * @param bool $log
     *            Defina True para registrar o modo de espera ou False para não registrar.
     * @return void
     */
    protected function sleepStage(int $seconds, bool $log = true)
    {
        $waitTime = (time() + $seconds);
        $this->modem->setStageData('monitor.waitTime', $waitTime);

        if ($log) {

            $this->modem->setStageData('monitor.sleeping', true);
            $this->logger->logNotice('next stage after: %s', date('Y/m/d H:i:s', $waitTime));
        }
    }

    /**
     * Envia uma mensagem para o modem e marca a conexão como exclusiva.
     *
     * @param string $message
     *            Mensagem do comando Modbus.
     * @return bool True quando a mensagem foi enviada.
     *         False quando contrário.
     */
    protected function sendMessage(string $message): bool
    {
        if ($this->connection->writeMessage($message)) {

            $this->connection->lock($this->modem);
            return true;
        }

        return false;
    }

    /**
     * Recebe uma mensagem do modem e desmarca a conexão como exclusiva.
     *
     * @param string $message
     *            Mensagem de resposta Modbus (Atualizado por referência).
     * @param int $length
     *            Comprimento esperado para mensagem.
     * @return bool rue quando a mensagem foi recebida.
     *         False quando contrário.
     */
    protected function receiveMessage(string &$message, int $length): bool
    {
        $message = '';

        if ($this->connection->readMessage($message, $length)) {

            $this->connection->unlock($this->modem);
            return true;
        }

        return false;
    }

    /**
     * Quando a objeto callable é executado.
     * Inicia as configurações para execução da ação.
     *
     * @param MonitorStageAction $action
     *            Instância com informações da ação.
     * @return void
     */
    public function __invoke(MonitorStageAction $action)
    {
        $this->connection = $action->getConnection();
        $this->manager = $action->getModemManager();
        $this->modem = $action->getModemEntity();
        $this->logger = $action->getLogger();

        if (! $this->stageWaiting() && ! $this->modemWaiting()) {

            try {
                if ($this->execute()) {
                    sleep(2); // Impede sobrecarga do gateway e do servidor local.
                }
            } catch (\Exception $exception) {
                $this->logger->logException($exception);
            }
        }
    }
}