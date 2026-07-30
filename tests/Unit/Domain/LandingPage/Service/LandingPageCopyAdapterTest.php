<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\LandingPage\Service;

use Bcoem\Domain\LandingPage\Service\LandingPageCopyAdapter;
use PHPUnit\Framework\TestCase;

final class LandingPageCopyAdapterTest extends TestCase
{
    public function test_en_us_catalog_preserves_landing_page_copy(): void
    {
        $copy = (new LandingPageCopyAdapter())->forLocale('en-US');

        self::assertSame('Register', $copy->register);
        self::assertSame('Log In', $copy->login);
        self::assertSame('Log Out', $copy->logout);
        self::assertSame('Competition Officials', $copy->officials);
        self::assertSame('Past Winners', $copy->pastWinners);
        self::assertSame('At a glance', $copy->atAGlance);
        self::assertSame('Drop-off locations', $copy->dropoffLocations);
        self::assertSame('Register as a steward', $copy->registerSteward);
        self::assertSame('Judge and steward registration will open %s.', $copy->judgeUpcomingMessage);
        self::assertSame('Return to top', $copy->returnToTop);
        self::assertSame('%s logo', $copy->hostLogoAlt);
        self::assertSame('Judge Registration', $copy->judgeRegistrationCardTitle);
        self::assertSame('Steward Registration', $copy->stewardRegistrationCardTitle);
        self::assertSame('Status', $copy->cardStatusLabel);
        self::assertSame('Info', $copy->cardInfoLabel);
        self::assertSame(
            'The limit of %d entries has been reached. No further entries will be accepted.',
            $copy->entryLimitMessage,
        );
        self::assertSame(
            'The entry limit nearly reached! %d of %d maximum entries have been added into the system.',
            $copy->nearLimitMessage,
        );
        self::assertSame(
            'The limit of %d paid entries has been reached. No further entries will be accepted.',
            $copy->paidEntryLimitMessage,
        );
        self::assertSame(
            'Account registration will open %s. Please return then to register your account.',
            $copy->upcomingMessage,
        );
    }

    public function test_en_gb_catalog_preserves_british_landing_page_copy(): void
    {
        $copy = (new LandingPageCopyAdapter())->forLocale('en-GB');

        self::assertSame('Entry Info', $copy->entryInfo);
        self::assertSame('Competition Officials', $copy->officials);
        self::assertSame('Past Winners', $copy->pastWinners);
        self::assertSame('Judge and steward registration will open %s.', $copy->judgeUpcomingMessage);
        self::assertSame('Return to top', $copy->returnToTop);
        self::assertSame('%s logo', $copy->hostLogoAlt);
        self::assertSame('Judge Registration', $copy->judgeRegistrationCardTitle);
        self::assertSame('Steward Registration', $copy->stewardRegistrationCardTitle);
        self::assertSame('Status', $copy->cardStatusLabel);
        self::assertSame('Info', $copy->cardInfoLabel);
        self::assertSame(
            'The entry limit nearly reached! %d of %d maximum entries have been added into the system.',
            $copy->nearLimitMessage,
        );
        self::assertSame(
            'The limit of %d paid entries has been reached. No further entries will be accepted.',
            $copy->paidEntryLimitMessage,
        );
        self::assertSame('Winning entries have not been posted yet. Please check back later.', $copy->winnerDelayMessage);
    }

    public function test_es_419_catalog_preserves_landing_page_copy(): void
    {
        $copy = (new LandingPageCopyAdapter())->forLocale('es-419');

        self::assertSame('Registrarse', $copy->register);
        self::assertSame('Iniciar Sesión', $copy->login);
        self::assertSame('Cerrar Sesión', $copy->logout);
        self::assertSame('Funcionarios de la Competencia', $copy->officials);
        self::assertSame('Ganadores Anteriores', $copy->pastWinners);
        self::assertSame('De un vistazo', $copy->atAGlance);
        self::assertSame('Lugares de entrega', $copy->dropoffLocations);
        self::assertSame('Registrarse como auxiliar', $copy->registerSteward);
        self::assertSame('La inscripción de jueces y auxiliares se abrirá el %s.', $copy->judgeUpcomingMessage);
        self::assertSame('Volver al inicio', $copy->returnToTop);
        self::assertSame('Logotipo de %s', $copy->hostLogoAlt);
        self::assertSame('Registro de jueces', $copy->judgeRegistrationCardTitle);
        self::assertSame('Registro de auxiliares', $copy->stewardRegistrationCardTitle);
        self::assertSame('Estado', $copy->cardStatusLabel);
        self::assertSame('Información', $copy->cardInfoLabel);
        self::assertSame('Próximamente', $copy->statusUpcoming);
        self::assertSame('Abierto', $copy->statusOpen);
        self::assertSame('Cerrado', $copy->statusClosed);
        self::assertSame(
            'Se ha alcanzado el límite de %d entradas. No se aceptarán más entradas.',
            $copy->entryLimitMessage,
        );
        self::assertSame(
            '¡Casi se ha alcanzado el límite de inscripción! Se han agregado %d de un máximo de %d entradas al sistema.',
            $copy->nearLimitMessage,
        );
        self::assertSame(
            'Se ha alcanzado el límite de %d entradas pagadas. No se aceptarán más entradas.',
            $copy->paidEntryLimitMessage,
        );
        self::assertSame(
            'La inscripción de cuentas se abrirá el %s. Por favor, regresa entonces para registrar tu cuenta.',
            $copy->upcomingMessage,
        );
    }

    public function test_unknown_locale_falls_back_to_en_us_without_loading_a_dynamic_path(): void
    {
        $copy = (new LandingPageCopyAdapter())->forLocale('../../site/config');

        self::assertSame('Register', $copy->register);
        self::assertSame('Results', $copy->results);
    }
}
