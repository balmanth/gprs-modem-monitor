<?php
declare(strict_types = 1);
namespace GPRS\System\Monitor\ABS\Modem\Actions;

/**
 * Obtém as informações do hardware do modem.
 *
 * @since 1.0
 * @version 1.0
 * @author Silas B. Domingos
 * @copyright Silas B. Domingos
 * @package GPRS\System\Monitor\ABS\Modem\Actions
 */
final class GetDeviceInfoAction extends \GPRS\System\Monitor\ABS\Common\Actions\AbstractGetDeviceInfoAction
{

    /**
     * Endereço de leitura das informações.
     *
     * @var int
     */
    const R_DEVINFO_ADDRRES = 64050;

    /**
     *
     * {@inheritDoc}
     *
     * @see GPRS\System\Monitor\ABS\Common\Actions\AbstractGetDeviceInfoAction::getReadAddress()
     */
    protected function writeCommand(): bool
    {
        if ((bool) $this->modem->getData('modem.signal')) {
            return parent::writeCommand();
        }

        return false;
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see GPRS\System\Monitor\ABS\Common\Actions\AbstractGetDeviceInfoAction::getReadAddress()
     */
    protected function getReadAddress(): int
    {
        return self::R_DEVINFO_ADDRRES;
    }
}