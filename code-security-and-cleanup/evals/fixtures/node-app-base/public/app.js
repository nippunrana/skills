let showArchived = false;

function toggleArchive() {
  showArchived = !showArchived;
  loadNotes();
}

async function loadNotes() {
  const token = sessionStorage.getItem('notewell-token');
  const response = await fetch('/api/notes', { headers: { Authorization: `Bearer ${token}` } });
  const list = document.getElementById('notes');
  list.replaceChildren();
  if (!response.ok) return;

  const { notes } = await response.json();
  for (const note of notes) {
    if (note.archived && !showArchived) continue;
    const item = document.createElement('li');
    item.textContent = note.title;
    list.append(item);
  }
}

loadNotes();
