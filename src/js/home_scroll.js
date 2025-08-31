import axios from 'axios';

const filterInput = document.getElementById('filter-input');
const tableBody = document.getElementById('word-table-body');
const scrollContainer = document.getElementById('scroll-container');
const filterCheckbox = document.getElementById('filter-checkbox');

// State tracking
let query = '';
let lang = '';
let loading = false;
let initialLoaded = false;
let pivotId = null;
let currentWords = [];
let idAbcList = [];


let pivotEngDown = null;
let pivotEngUp = null;

// Detect language of input text
function detectLanguage(text) {
    return /[а-яА-ЯЁё]/.test(text) ? 'rus' : 'eng';
}

// Create table row element for a word
function createRow(word) {
    const tr = document.createElement('tr');
    tr.dataset.id = word.id;// <-- Add 'td-eng'
    const tdEng = document.createElement('td');
    tdEng.className = 'td-eng w-4/12 p-2 bg-white  border border-black rounded-lg py-1'; // <-- Add 'td-eng'
    tdEng.textContent = word.eng || 'No word';

    const gap = document.createElement('td');
    gap.className = 'w-1/12 bg-white rounded-lg';

    const tdRus = document.createElement('td');
    tdRus.className = 'td-rus w-4/12 p-2 bg-white border border-black rounded-lg py-1'; // <-- Add 'td-rus'
    tdRus.textContent = word.rus || 'Нет перевода';

    const gapBox = document.createElement('td');
    gapBox.className = 'w-1/12 bg-white rounded-lg text-center';

    const gapBin = document.createElement('td');
    gapBin.className = 'w-1/12 bg-white rounded-lg text-center align-middle';

    const trashIcon = document.createElement('span');
    trashIcon.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" class="inline h-5 w-5 text-gray-300 cursor-pointer" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3" />
    </svg>
    `;
    gapBin.appendChild(trashIcon);

    trashIcon.addEventListener('mouseenter', () => {
        trashIcon.querySelector('svg').classList.remove('text-gray-200');
        trashIcon.querySelector('svg').classList.add('text-gray-900');
    });
    trashIcon.addEventListener('mouseleave', () => {
        trashIcon.querySelector('svg').classList.remove('text-gray-900');
        trashIcon.querySelector('svg').classList.add('text-gray-200');
    });

    // Delete row in DB and DOM on click
    trashIcon.addEventListener('click', () => {
        if (confirm('Are you sure you want to delete this word?')) {
            axios.delete(`/dictionary/${word.id}`)
                .then(() => {
                    tr.remove();
                })
                .catch(error => {
                    alert('Failed to delete word.');
                    console.error(error);
                });
        }
    });

    const gapEdit = document.createElement('td');
    gapEdit.className = 'w-1/12 bg-white rounded-lg text-center align-middle';

    const editIcon = document.createElement('span');
    editIcon.className = 'edit-icon'; // <-- Correct place!
    editIcon.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" class="inline h-5 w-5 text-gray-300 cursor-pointer ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m2 2H7a2 2 0 01-2-2v-4a2 2 0 012-2h4a2 2 0 012 2v4a2 2 0 01-2 2z" />
    </svg>
    `;

    gapEdit.appendChild(editIcon);

    // Add hover effect for edit icon
    editIcon.addEventListener('mouseenter', () => {
        editIcon.querySelector('svg').classList.remove('text-gray-300');
        editIcon.querySelector('svg').classList.add('text-gray-900');
    });
    editIcon.addEventListener('mouseleave', () => {
        editIcon.querySelector('svg').classList.remove('text-gray-900');
        editIcon.querySelector('svg').classList.add('text-gray-300');
    });

    //maybe to be removed from code
    const tdRadio = document.createElement('td');
    tdRadio.className = 'w-[40px] p-2 text-center';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = word.id;
    checkbox.checked = word.learnt === 1; // ✅ auto-check if already learnt

    checkbox.addEventListener('change', () => {
        markWordAsLearnt(word.id, checkbox.checked ? 1 : 0);
    });
    checkbox.className = 'form-checkbox accent-black border border-black';

    gapBox.appendChild(checkbox);

    tr.appendChild(tdEng);
    tr.appendChild(gap);
    tr.appendChild(tdRus);
    tr.appendChild(gapBox);
    tr.appendChild(gapBin);
    tr.appendChild(gapEdit);
    tr.appendChild(tdRadio);

    return tr;
}

filterCheckbox.addEventListener('change', () => {
    if (query) {
        // If user has typed something in input -> filter mode
        filterWords();
    } else {
        // No query -> initial scroll mode
        fetchInitialScrollWords();
    }
});

// Trigger initial dictionary load (when there's no input filter)
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM ready');
    fetchInitialScrollWords();
});

// Mark a word as learnt (API call)
function markWordAsLearnt(id, learnt) {
    axios.patch(`/dictionary/${id}/learn`, { learnt }, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    }).then(() => {
    })
        .catch(error => {
            console.error('Failed to update word:', error);
            alert('Could not update word.');
        });
}

function fetchInitialWords() {
    if (!query) {
        scrollContainer.dataset.mode = 'initial';
        fetchInitialScrollWords(); // reload initial words
        return;
    }

    loading = true;
    initialLoaded = false;
    tableBody.innerHTML = '';
    currentWords = [];

    // 👇 build params object instead of string concatenation
    const params = {
        query: query,
        lang: lang,
        page: 1
    };
    if (filterCheckbox.checked) {
        params.learntOnly = 1; // ✅ add learntOnly if checkbox is ticked
    }

    axios.get('/dictionary/chunk', { params })
        .then(response => {
            const data = response.data.data || [];
            tableBody.innerHTML = '';
            currentWords = [];

            if (data.length > 0) {
                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.appendChild(row);
                    currentWords.push(word);
                });
                pivotId = currentWords[0].id;
                initialLoaded = true;
            } else {
                pivotId = null;
                initialLoaded = false;
            }
        })
        .finally(() => loading = false);
}

scrollContainer.addEventListener('scroll', () => {
    if (!initialLoaded || loading) return;

    const scrollTop = scrollContainer.scrollTop;
    const scrollHeight = scrollContainer.scrollHeight;
    const containerHeight = scrollContainer.clientHeight;

    if (scrollTop + containerHeight >= scrollHeight - 20) {
        fetchScrollWords('down');
    }

    if (scrollTop <= 20) {
        fetchScrollWords('up');
    }
});

// Listen for changes on the filter-checkbox
function filterWords() {
    const query = filterInput.value.trim();
    const params = {};

    if (query) {
        params.query = query;
    }
    if (filterCheckbox && filterCheckbox.checked) {
        params.learntOnly = 1;
    }

    axios.get('/dictionary/filter', { params })
        .then(response => {
            const data = response.data.data || [];
            tableBody.innerHTML = '';
            currentWords = [];
            data.forEach(word => {
                const row = createRow(word);

                tableBody.appendChild(row);

                currentWords.push(word);
            });
        })
        .catch(console.error);
}

let debounceTimer = null;
filterInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        query = filterInput.value.trim();
        lang = detectLanguage(query);

        if (!query) {
            scrollContainer.dataset.mode = 'initial';
            fetchInitialScrollWords(); // show default 20
            return;
        }

        // 🔹 Autocomplete request
        axios.get(`/dictionary/autocomplete?query=${query}&lang=${lang}`)
            .then(res => showSuggestions(res.data))
            .catch(console.error);

        // 🔹 Filtered table request
        scrollContainer.dataset.mode = 'filtered';
        fetchInitialWords();
    }, 300);
});

let editingRowId = null;

// 1. Handle edit icon click
document.addEventListener('click', function (e) {
    if (e.target.closest('.edit-icon')) {
        const tr = e.target.closest('tr');
        editingRowId = tr.dataset.id; // assuming you set tr.dataset.id = word.id when creating the row
        document.getElementById('update-input').value = tr.querySelector('.td-eng').textContent.trim();
        document.getElementById('update-input_2').value = tr.querySelector('.td-rus').textContent.trim();
    }
});

/* document.getElementById('save-btn').addEventListener('click', function () {
    const eng = document.getElementById('update-input').value.trim();
    const rus = document.getElementById('update-input_2').value.trim();
    const messageDiv = document.getElementById('save-message');
    messageDiv.textContent = ''; // Clear previous message

    if (!eng || !rus) {
        messageDiv.textContent = 'Both fields are required.';
        return;
    }

    if (editingRowId) {
        axios.patch(`/dictionary/${editingRowId}`, { eng, rus })
            .then(() => {
                const tr = document.querySelector(`tr[data-id="${editingRowId}"]`);
                if (tr) {
                    tr.querySelector('.td-eng').textContent = eng;
                    tr.querySelector('.td-rus').textContent = rus;
                }
                editingRowId = null;
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';
            })
            .catch(() => {
                messageDiv.textContent = 'Failed to update word.';
            });
    } else {

        axios.post('/dictionary', { eng, rus })
            .then(response => {
                console.log('SUCCESS:', response);

                // Clear input fields
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';

                // Перерисовываем таблицу словами из ответа store()
                const data = response.data.data || [];
                tableBody.innerHTML = '';
                currentWords = [];


                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.appendChild(row);
                    currentWords.push(word);
                });
                console.log(data);
                pivotId = currentWords[currentWords.length - 1]?.id || null;
                initialLoaded = true;
            })
            
            .catch(error => {
                console.log('ERROR:', error.response ? error.response.data : error.message);
            }); 
    }
}); */
document.getElementById('save-btn').addEventListener('click', function () {
    const eng = document.getElementById('update-input').value.trim();
    const rus = document.getElementById('update-input_2').value.trim();
    const messageDiv = document.getElementById('save-message');
    messageDiv.textContent = '';

    if (!eng || !rus) {
        messageDiv.textContent = 'Both fields are required.';
        return;
    }

    if (editingRowId) {
        // PATCH existing word
        axios.patch(`/dictionary/${editingRowId}`, { eng, rus })
            .then(() => {
                const tr = document.querySelector(`tr[data-id="${editingRowId}"]`);
                if (tr) {
                    tr.querySelector('.td-eng').textContent = eng;
                    tr.querySelector('.td-rus').textContent = rus;
                }
                editingRowId = null;
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';
            })
            .catch(() => messageDiv.textContent = 'Failed to update word.');
    } else {
        // POST new word
        axios.post('/dictionary', { eng, rus })
            .then(response => {
                const newWord = response.data.data[0]; // assume API returns saved word

                // Add the new word at the top
                const row = createRow(newWord);
                tableBody.insertBefore(row, tableBody.firstChild);
                currentWords.unshift(newWord);

                // Update scroll pivots
                pivotEngUp = currentWords[0].eng;
                if (!pivotEngDown) pivotEngDown = currentWords[currentWords.length - 1].eng;

                // Clear inputs
                document.getElementById('update-input').value = '';
                document.getElementById('update-input_2').value = '';
            })
            .catch(error => {
                console.error('Failed to save word:', error.response?.data || error.message);
            });
    }
});

/* function fetchScrollWords(direction = 'down') {
    if (loading) return;
    loading = true;

    let pivotEng;

    if (currentWords.length === 0) {
        // fallback: use first/last row in table if currentWords empty
        const rows = tableBody.querySelectorAll('tr');
        if (rows.length === 0) {
            loading = false;
            return; // nothing to scroll
        }
        pivotEng = direction === 'down'
            ? rows[rows.length - 1].querySelector('.td-eng').textContent
            : rows[0].querySelector('.td-eng').textContent;
    } else {
        pivotEng = direction === 'down'
            ? currentWords[currentWords.length - 1].eng
            : currentWords[0].eng;
    }

    const params = {
        pivot: pivotEng,
        direction,
        learntOnly: filterCheckbox.checked ? 1 : 0
    };
    console.log(params);
    axios.get('/dictionary/scroll', { params })
        .then(response => {
            const data = response.data.data || [];
            if (!data.length) return;

            if (direction === 'down') {
                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.appendChild(row);
                    currentWords.push(word);
                });
            } else {
                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.insertBefore(row, tableBody.firstChild);
                    currentWords.unshift(word);
                });
            }
        })
        .catch(err => console.error("Scroll fetch failed:", err))
        .finally(() => loading = false);
} */

/* function fetchScrollWords(direction = 'down') {
    if (loading) return;
    loading = true;

    let pivotEng = direction === 'down' ? pivotEngDown : pivotEngUp;

    // If pivot is null, fallback to currentWords or table rows
    if (!pivotEng) {
        if (currentWords.length) {
            pivotEng = direction === 'down'
                ? currentWords[currentWords.length - 1].eng
                : currentWords[0].eng;
        } else {
            const rows = tableBody.querySelectorAll('tr');
            if (!rows.length) {
                loading = false;
                return;
            }
            pivotEng = direction === 'down'
                ? rows[rows.length - 1].querySelector('.td-eng').textContent
                : rows[0].querySelector('.td-eng').textContent;
        }
    }

    const params = {
        pivot: pivotEng,
        direction,
        learntOnly: filterCheckbox.checked ? 1 : 0
    };

    axios.get('/dictionary/scroll', { params })
        .then(response => {
            const data = response.data.data || [];
            if (!data.length) return;

            if (direction === 'down') {
                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.appendChild(row);
                    currentWords.push(word);
                });
                pivotEngDown = currentWords[currentWords.length - 1].eng; // update down pivot
            } else {
                data.reverse().forEach(word => {
                    const row = createRow(word);
                    tableBody.insertBefore(row, tableBody.firstChild);
                    currentWords.unshift(word);
                });
                pivotEngUp = currentWords[0].eng; // update up pivot
            }
        })
        .catch(err => console.error("Scroll fetch failed:", err))
        .finally(() => loading = false);
}
         */
function fetchInitialScrollWords() {
    if (query) return; // Don't run if query is active

    loading = true;

    const params = {};
    if (filterCheckbox && filterCheckbox.checked) {
        params.learntOnly = 1;
    }

    axios.get('/dictionary/initial', { params })
        .then(response => {
            const data = response.data.data || [];
            tableBody.innerHTML = '';
            currentWords = [];

            data.forEach(word => {
                const row = createRow(word);
                tableBody.appendChild(row);
                currentWords.push(word);
            });

            pivotId = currentWords[currentWords.length - 1]?.id || null;
            initialLoaded = true;

            // 🔹 Initialize idAbcList with initial words
            idAbcList = currentWords.map(w => w.id);

        })
        .catch(console.error)
        .finally(() => loading = false);
}

function fetchScrollWords(direction = 'down') {
    if (loading) return;
    loading = true;

    let pivotEng = direction === 'down' ? pivotEngDown : pivotEngUp;

    if (!pivotEng) {
        if (currentWords.length) {
            pivotEng = direction === 'down'
                ? currentWords[currentWords.length - 1].eng
                : currentWords[0].eng;
        } else {
            const rows = tableBody.querySelectorAll('tr');
            if (!rows.length) {
                loading = false;
                return;
            }
            pivotEng = direction === 'down'
                ? rows[rows.length - 1].querySelector('.td-eng').textContent
                : rows[0].querySelector('.td-eng').textContent;
        }
    }

    const params = {
        pivot: pivotEng,
        direction,
        learntOnly: filterCheckbox.checked ? 1 : 0
    };

    axios.get('/dictionary/scroll', { params })
        .then(response => {
            const data = response.data.data || [];
            if (!data.length) return;

            if (direction === 'down') {
                data.forEach(word => {
                    const row = createRow(word);
                    tableBody.appendChild(row);
                    currentWords.push(word);
                });
                pivotEngDown = currentWords[currentWords.length - 1].eng;
            } else {
                data.reverse().forEach(word => {
                    const row = createRow(word);
                    tableBody.insertBefore(row, tableBody.firstChild);
                    currentWords.unshift(word);
                });
                pivotEngUp = currentWords[0].eng;
            }
        })
        .catch(err => console.error("Scroll fetch failed:", err))
        .finally(() => loading = false);
}
