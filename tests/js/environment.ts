/**
 * Browser APIs jsdom lacks but the code under test needs. Runs before any
 * module is loaded.
 */
import { TextDecoder, TextEncoder } from 'util';

// react-router reads TextEncoder when it loads.
Object.assign(globalThis, { TextEncoder, TextDecoder });
