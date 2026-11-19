import { wordCount } from '../lib/text.js';

export default function addWordCount(note) {
  return { ...note, wordCount: wordCount(note.body) };
}
