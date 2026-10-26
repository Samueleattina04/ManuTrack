/*
 * ManuTrack — script principale (leggero: niente 3D né grafici qui;
 * quei moduli si caricano con import() solo nelle pagine che li usano).
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import theme from './ui/theme';
import shortcuts from './ui/shortcuts';
import commandPalette from './ui/command-palette';
import tilt from './ui/tilt';
import countUp from './ui/count-up';

Alpine.data('theme', theme);
Alpine.data('shortcuts', shortcuts);
Alpine.data('commandPalette', commandPalette);
Alpine.directive('tilt', tilt);
Alpine.directive('count-up', countUp);

Livewire.start();
