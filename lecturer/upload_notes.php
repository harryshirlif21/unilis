<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];
// Fetch units taught by lecturer
$units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name 
        FROM units u 
        JOIN lecturer_units lu ON u.id = lu.unit_id 
        WHERE lu.lecturer_id = ?
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching units: " . $e->getMessage());
    $units = [];
}

// Function to fetch existing topics for a unit
function getTopics($conn, $unit_id) {
    $topics = [];
    try {
        $stmt = $conn->prepare("
            SELECT id, title, subtopics_json, uploaded_at
            FROM classnotes
            WHERE unit_id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['subtopics'] = json_decode($row['subtopics_json'], true) ?: [];
            $topics[] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching topics: " . $e->getMessage());
    }
    return $topics;
}

// --- Handle AJAX request for existing topics ---
if(isset($_GET['getTopics']) && isset($_GET['unit_id'])){
    header('Content-Type: application/json');
    echo json_encode(getTopics($conn, intval($_GET['unit_id'])));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lecturer Notes Creator</title>

<style>
:root{
    --topic-color: #1f6feb;
    --subtopic-color: #0ea5a4;
    --file-label-color: #f59e0b;
    --image-label-color: #ef4444;
}
body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
.container { display: flex; gap: 20px; }
.form-section, .preview-section { background: white; padding: 20px; border-radius: 10px; width: 50%; overflow-y: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.topic-block { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; position: relative; }
.subtopic-block { margin-left: 20px; margin-top: 10px; padding: 10px; border-left: 3px solid #888; position: relative; }
input[type="text"], textarea, select { width: 100%; margin-top: 8px; padding: 8px; box-sizing: border-box; }
button { padding: 6px 12px; margin-top: 10px; cursor: pointer; }
.label-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; color: white; font-size: 12px; margin-right:8px; }
.topic-pill { background: var(--topic-color); }
.subtopic-pill { background: var(--subtopic-color); }
.file-pill { background: var(--file-label-color); }
.image-pill { background: var(--image-label-color); }
.contenteditable { border:1px solid #ccc; min-height:50px; padding:6px; border-radius:6px; margin-top:4px; }
.choice-row { display:flex; gap:8px; align-items:center; margin-top:6px; }
.remove-btn { background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; }
.edit-btn { background:#10b981; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; margin-left:5px; }
.unit-select { margin-bottom:15px; }
#existingTopics { margin-top:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
img.inline-img { max-width:200px; display:inline-block; margin:4px; border-radius:6px; }
</style>

</head>
<body>

<h1>Lecturer Notes Creator</h1>

<div class="unit-select">
    <label>Select Unit: 
        <select id="unitDropdown">
            <option value="">-- Select Unit --</option>
            <?php foreach($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</div>

<div class="container">
    <div class="form-section">
        <h2>Input Notes</h2>
        <div id="topics"></div>
        <button onclick="addTopic()">+ Add Topic</button>
        <hr>
       <hr>
<button onclick="submitNotes()" style="background: #10b981; color: white;">💾 Save as New Notes</button>
<button onclick="updateNotes()" style="background: #3b82f6; color: white;">✏️ Update Existing Notes</button>
<div style="margin-top: 10px; font-size: 12px; color: #666;">
    <strong>Instructions:</strong> 
    • Use "Save as New" to create new notes<br>
    • Use "Edit" button on existing topics, then "Update Existing" to save changes
</div>
    </div>

    <div class="preview-section">
        <h2>Live Preview</h2>
        <div id="preview"></div>
    </div>
</div>

<div id="existingTopics">
    <h3>Already Added Topics in this Unit</h3>
    <div id="topicsList"></div>
</div>

<script>
// ---------------------------------------------------------
// FULL JAVASCRIPT BEGINS HERE — DATA MODEL + STRUCTURES
// ---------------------------------------------------------

let topics = [];
let existingTopics = [];
let selectedUnitId = null;

const unitDropdown = document.getElementById('unitDropdown');
unitDropdown.addEventListener('change', loadUnitTopics);

function generateId() {
    return Date.now() + Math.floor(Math.random() * 1000);
}

function loadUnitTopics() {
    selectedUnitId = unitDropdown.value;

    if (!selectedUnitId) {
        topics = [];
        existingTopics = [];
        renderForm();
        renderExistingTopics();
        return;
    }

    fetch(`?getTopics=1&unit_id=${selectedUnitId}`)
        .then(res => res.json())
        .then(data => {
            existingTopics = data;
            topics = [];
            renderForm();
            renderExistingTopics();
        });
}

// Proper HTML escaping function (replaces the problematic escape() function)
function htmlEscape(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Escape for HTML attribute values (for input values)
function attrEscape(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/"/g, '&quot;');
}

// ---------------------------------------------------------
// TOPIC & SUBTOPIC CREATION
// ---------------------------------------------------------

function addTopic() {
    topics.push({
        id: generateId(),
        title: "",
        subtopics: []
    });
    renderForm();
}

function removeTopic(id) {
    topics = topics.filter(t => t.id !== id);
    renderForm();
}

function addSubtopic(topicId) {
    const t = topics.find(x => x.id === topicId);
    t.subtopics.push({
        id: generateId(),
        title: "",
        content: "",      // rich text HTML
        choices: [],
        correctChoice: null,
        images: [],       // inline images {id, file, placeholder}
        files: []         // attachment files {id, file, label}
    });
    renderForm();
}

function removeSubtopic(topicId, subId) {
    const t = topics.find(x => x.id === topicId);
    t.subtopics = t.subtopics.filter(s => s.id !== subId);
    renderForm();
}

// ---------------------------------------------------------
// RENDER FORM
// ---------------------------------------------------------

function renderForm() {
    const container = document.getElementById("topics");
    container.innerHTML = "";

    topics.forEach(topic => {
        const topicDiv = document.createElement("div");
        topicDiv.className = "topic-block";

        topicDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between;">
                <div>
                    <label class='topic-pill label-pill'>Topic</label>
                    <input type='text' value='${attrEscape(topic.title)}'
                        oninput="updateTopicTitle(${topic.id}, this.value)">
                </div>
                <button class="remove-btn" onclick="removeTopic(${topic.id})">Delete</button>
            </div>

            <div id="subtopics-${topic.id}"></div>
            <button onclick="addSubtopic(${topic.id})">+ Add Subtopic</button>
        `;

        container.appendChild(topicDiv);

        // Render subtopics
        const subsDiv = document.getElementById(`subtopics-${topic.id}`);

        topic.subtopics.forEach(sub => {
            const subDiv = document.createElement("div");
            subDiv.className = "subtopic-block";

            const contentId = `content-${sub.id}`;

            subDiv.innerHTML = `
                <div style="display:flex; justify-content:space-between;">
                    <div style="flex:1;">
                        <label class='subtopic-pill label-pill'>Subtopic</label>
                        <input type="text" value="${attrEscape(sub.title)}"
                            oninput="updateSubtopicTitle(${topic.id}, ${sub.id}, this.value)">
                    </div>

                    <button class="remove-btn" onclick="removeSubtopic(${topic.id}, ${sub.id})">Delete</button>
                </div>

                <label>Content (with images):</label>
                <div class="contenteditable" id="${contentId}" contenteditable="true"
                    onkeyup="updateSubtopicContent(${topic.id}, ${sub.id})"
                    onclick="storeCursorPosition(${sub.id})">
                </div>

                <input type="file" accept="image/*" 
                       onchange="insertInlineImage(event, ${topic.id}, ${sub.id})">

                <h4>Choices</h4>
                <div id="choices-${sub.id}"></div>
                <button onclick="addChoice(${topic.id}, ${sub.id})">+ Choice</button>

                <h4>Attach Files (not inline)</h4>
                <input type="file" multiple onchange="addSubtopicFiles(event, ${topic.id}, ${sub.id})">
                <div id="files-${sub.id}"></div>
            `;

            subsDiv.appendChild(subDiv);

            // Restore HTML content when editing
            document.getElementById(contentId).innerHTML = sub.content;

            // Render choices
            renderChoices(topic.id, sub.id);

            // Render files
            renderSubtopicFiles(topic.id, sub.id);
        });
    });

    renderPreview();
}

// ---------------------------------------------------------
// UPDATE FUNCTIONS
// ---------------------------------------------------------

function updateTopicTitle(topicId, val) {
    const t = topics.find(x => x.id === topicId);
    t.title = val;
    renderPreview();
}

function updateSubtopicTitle(topicId, subId, val) {
    const t = topics.find(x => x.id === topicId);
    const s = t.subtopics.find(x => x.id === subId);
    s.title = val;
    renderPreview();
}

function updateSubtopicContent(topicId, subId) {
    const div = document.getElementById(`content-${subId}`);
    const html = div.innerHTML;

    const t = topics.find(x => x.id === topicId);
    const s = t.subtopics.find(x => x.id === subId);

    s.content = html;
    renderPreview();
}

// ---------------------------------------------------------
// INLINE IMAGE INSERTION
// ---------------------------------------------------------

let cursorPositions = {};

function storeCursorPosition(subId) {
    const sel = window.getSelection();
    if (sel.rangeCount > 0) {
        cursorPositions[subId] = sel.getRangeAt(0);
    }
}

function insertInlineImage(event, topicId, subId) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = function (e) {
        const t = topics.find(x => x.id === topicId);
        const s = t.subtopics.find(x => x.id === subId);

        const placeholder = "img_" + generateId();

        // Save image reference (will upload later server-side)
        s.images.push({
            id: generateId(),
            file: file,
            placeholder: placeholder
        });

        // Create image with data URL for preview and placeholder for server replacement
        const imgHTML = `<img src="${e.target.result}" 
                           class="inline-img" 
                           data-placeholder="${placeholder}">`;

        const div = document.getElementById(`content-${subId}`);

        let range = cursorPositions[subId];
        if (!range) {
            range = document.createRange();
            range.selectNodeContents(div);
            range.collapse(false);
        }

        const temp = document.createElement("div");
        temp.innerHTML = imgHTML;
        const imgNode = temp.firstChild;

        range.insertNode(imgNode);

        // Move cursor after the image
        range.setStartAfter(imgNode);
        range.setEndAfter(imgNode);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        // Update HTML content
        s.content = div.innerHTML;
        renderPreview();
        
        // Clear the file input
        event.target.value = '';
    };

    reader.readAsDataURL(file);
}

// ---------------------------------------------------------
// CHOICES (MCQ)
// ---------------------------------------------------------

function addChoice(topicId, subId) {
    const t = topics.find(x => x.id === topicId);
    const s = t.subtopics.find(x => x.id === subId);

    s.choices.push({
        id: generateId(),
        text: ""
    });

    renderChoices(topicId, subId);
    renderPreview();
}

function removeChoice(topicId, subId, choiceId) {
    const s = topics.find(t => t.id === topicId).subtopics.find(s => s.id === subId);
    s.choices = s.choices.filter(c => c.id !== choiceId);

    if (s.correctChoice === choiceId) s.correctChoice = null;

    renderChoices(topicId, subId);
    renderPreview();
}

function updateChoiceText(topicId, subId, choiceId, val) {
    const s = topics.find(t => t.id === topicId).subtopics.find(s => s.id === subId);
    const c = s.choices.find(c => c.id === choiceId);
    c.text = val;
    renderPreview();
}

function setCorrectChoice(topicId, subId, choiceId) {
    const s = topics.find(t => t.id === topicId).subtopics.find(s => s.id === subId);
    s.correctChoice = choiceId;
    renderChoices(topicId, subId);
    renderPreview();
}

function renderChoices(topicId, subId) {
    const s = topics.find(t => t.id === topicId).subtopics.find(s => s.id === subId);
    const container = document.getElementById(`choices-${subId}`);
    container.innerHTML = "";

    s.choices.forEach(choice => {
        const row = document.createElement("div");
        row.className = "choice-row";

        row.innerHTML = `
            <input type="radio" name="correct-${subId}" 
                   onclick="setCorrectChoice(${topicId}, ${subId}, ${choice.id})"
                   ${s.correctChoice === choice.id ? "checked" : ""}>

            <input type="text" 
                   value="${attrEscape(choice.text)}"
                   oninput="updateChoiceText(${topicId}, ${subId}, ${choice.id}, this.value)">

            <button class="remove-btn" 
                    onclick="removeChoice(${topicId}, ${subId}, ${choice.id})">X</button>
        `;

        container.appendChild(row);
    });
}

// ---------------------------------------------------------
// FILE ATTACHMENTS (non-inline)
// ---------------------------------------------------------

function addSubtopicFiles(event, topicId, subId) {
    const files = Array.from(event.target.files);

    const s = topics
        .find(t => t.id === topicId)
        .subtopics.find(s => s.id === subId);

    files.forEach(f => {
        s.files.push({
            id: generateId(),
            file: f,
            label: f.name
        });
    });

    renderSubtopicFiles(topicId, subId);
    renderPreview();
}

function renderSubtopicFiles(topicId, subId) {
    const s = topics
        .find(t => t.id === topicId)
        .subtopics.find(s => s.id === subId);

    const box = document.getElementById(`files-${subId}`);
    box.innerHTML = "";

    s.files.forEach(f => {
        const div = document.createElement("div");
        div.innerHTML = `
            <span class="file-pill label-pill">${htmlEscape(f.label)}</span>
            <button class="remove-btn" onclick="removeSubtopicFile(${topicId}, ${subId}, ${f.id})">X</button>
        `;
        box.appendChild(div);
    });
}

function removeSubtopicFile(topicId, subId, fileId) {
    const s = topics
        .find(t => t.id === topicId)
        .subtopics.find(s => s.id === subId);

    s.files = s.files.filter(f => f.id !== fileId);

    renderSubtopicFiles(topicId, subId);
    renderPreview();
}

// ---------------------------------------------------------
// PREVIEW
// ---------------------------------------------------------

function renderPreview() {
    const p = document.getElementById("preview");
    p.innerHTML = "";

    topics.forEach(topic => {
        const div = document.createElement("div");
        
        // Use textContent for text elements to avoid HTML escaping issues
        const topicTitle = document.createElement("h2");
        topicTitle.textContent = topic.title;
        div.appendChild(topicTitle);

        topic.subtopics.forEach(sub => {
            const sdiv = document.createElement("div");
            
            const subTitle = document.createElement("h3");
            subTitle.textContent = sub.title;
            sdiv.appendChild(subTitle);

            const contentDiv = document.createElement("div");
            contentDiv.innerHTML = sub.content; // This is already HTML
            sdiv.appendChild(contentDiv);

            // Choices
            if (sub.choices.length) {
                const ul = document.createElement("ul");
                sub.choices.forEach(c => {
                    const li = document.createElement("li");
                    li.textContent = c.text + (sub.correctChoice === c.id ? " (✔)" : "");
                    ul.appendChild(li);
                });
                sdiv.appendChild(ul);
            }

            // Files
            if (sub.files.length) {
                const fdiv = document.createElement("div");
                const strong = document.createElement("strong");
                strong.textContent = "Files:";
                fdiv.appendChild(strong);
                fdiv.appendChild(document.createElement("br"));
                
                sub.files.forEach(f => {
                    const span = document.createElement("span");
                    span.className = "file-pill label-pill";
                    span.textContent = f.label;
                    fdiv.appendChild(span);
                });
                sdiv.appendChild(fdiv);
            }

            div.appendChild(sdiv);
        });

        p.appendChild(div);
    });
}

// ---------------------------------------------------------
// EXISTING TOPICS (ALREADY SAVED)
// ---------------------------------------------------------

function renderExistingTopics() {
    const box = document.getElementById("topicsList");
    box.innerHTML = "";

    existingTopics.forEach(t => {
        const div = document.createElement("div");
        div.style.padding = "10px";
        div.style.margin = "5px 0";
        div.style.border = "1px solid #ddd";

        const titleSpan = document.createElement("strong");
        titleSpan.textContent = t.title;
        
        const editBtn = document.createElement("button");
        editBtn.className = "edit-btn";
        editBtn.textContent = "Edit";
        editBtn.onclick = () => loadForEditing(t.id);

        div.appendChild(titleSpan);
        div.appendChild(editBtn);
        box.appendChild(div);
    });
}

// Load topic for editing
function loadForEditing(topicId) {
    const t = existingTopics.find(x => x.id === topicId);
    if (!t) return;

    topics = [{
        id: t.id,
        title: t.title,
        subtopics: t.subtopics
    }];

    renderForm();
}

// ---------------------------------------------------------
// SUBMIT NOTES - IMPROVED VERSION
// ---------------------------------------------------------

function submitNotes() {
    if (!selectedUnitId) {
        alert("Select a unit first.");
        return;
    }

    if (topics.length === 0) {
        alert("Please add at least one topic before saving.");
        return;
    }

    // Validate that all topics have titles
    for (let topic of topics) {
        if (!topic.title.trim()) {
            alert("Please provide a title for all topics.");
            return;
        }
        
        // Validate subtopics
        for (let subtopic of topic.subtopics) {
            if (!subtopic.title.trim()) {
                alert("Please provide a title for all subtopics.");
                return;
            }
        }
    }

    const formData = new FormData();

    // Append main JSON
    formData.append("unit_id", selectedUnitId);
    formData.append("topics", JSON.stringify(topics));

    // Collect images + files for upload
    topics.forEach(topic => {
        topic.subtopics.forEach(sub => {
            // Inline images
            sub.images.forEach((img, index) => {
                formData.append(
                    `subtopic_images[${sub.id}][]`,
                    img.file,
                    img.file.name
                );
            });

            // Files
            sub.files.forEach(f => {
                formData.append(
                    `subtopic_files[${sub.id}][]`,
                    f.file,
                    f.file.name
                );
            });
        });
    });

    // Show loading state
    const saveBtn = document.querySelector('button[onclick="submitNotes()"]');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;

    fetch("saveClassnotes.php", {
        method: "POST",
        body: formData
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Network response was not ok');
        }
        return r.json();
    })
    .then(res => {
        alert(res.message);
        if (res.success) {
            // Clear the form and reload existing topics
            topics = [];
            renderForm();
            loadUnitTopics();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving notes: ' + error.message);
    })
    .finally(() => {
        // Restore button state
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

// Update notes function (placeholder - implement as needed)
function updateNotes() {
    alert("Update functionality to be implemented");
}
// ---------------------------------------------------------
// UPDATE NOTES FUNCTIONALITY
// ---------------------------------------------------------

function updateNotes() {
    if (!selectedUnitId) {
        alert("Select a unit first.");
        return;
    }

    if (topics.length === 0) {
        alert("Please load a topic to edit first.");
        return;
    }

    // Check if we're editing an existing topic
    const isEditing = topics[0] && existingTopics.find(t => t.id === topics[0].id);
    
    if (!isEditing) {
        alert("Please load an existing topic to edit using the 'Edit' button.");
        return;
    }

    // Validate that all topics have titles
    for (let topic of topics) {
        if (!topic.title.trim()) {
            alert("Please provide a title for all topics.");
            return;
        }
        
        // Validate subtopics
        for (let subtopic of topic.subtopics) {
            if (!subtopic.title.trim()) {
                alert("Please provide a title for all subtopics.");
                return;
            }
        }
    }

    const formData = new FormData();

    // Append main JSON with update flag
    formData.append("unit_id", selectedUnitId);
    formData.append("topics", JSON.stringify(topics));
    formData.append("action", "update");
    formData.append("topic_id", topics[0].id); // The topic being edited

    // Collect images + files for upload
    topics.forEach(topic => {
        topic.subtopics.forEach(sub => {
            // Inline images
            sub.images.forEach(img => {
                formData.append(
                    `subtopic_images[${sub.id}][]`,
                    img.file,
                    img.file.name
                );
            });

            // Files
            sub.files.forEach(f => {
                formData.append(
                    `subtopic_files[${sub.id}][]`,
                    f.file,
                    f.file.name
                );
            });
        });
    });

    // Show loading state
    const updateBtn = document.querySelector('button[onclick="updateNotes()"]');
    const originalText = updateBtn.textContent;
    updateBtn.textContent = 'Updating...';
    updateBtn.disabled = true;

    fetch("saveClassnotes.php", {
        method: "POST",
        body: formData
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Network response was not ok');
        }
        return r.json();
    })
    .then(res => {
        alert(res.message);
        if (res.success) {
            // Clear the form and reload existing topics
            topics = [];
            renderForm();
            loadUnitTopics();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating notes: ' + error.message);
    })
    .finally(() => {
        // Restore button state
        updateBtn.textContent = originalText;
        updateBtn.disabled = false;
    });
}

// Modify the loadForEditing function to show which topic is being edited
function loadForEditing(topicId) {
    const t = existingTopics.find(x => x.id === topicId);
    if (!t) return;

    topics = [{
        id: t.id,
        title: t.title,
        subtopics: t.subtopics
    }];

    renderForm();
    
    // Scroll to the form section and show a message
    document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
    alert(`Now editing: "${t.title}"\n\nMake your changes and click "Update Notes" to save.`);
}

// Add a function to clear current editing
function clearEditing() {
    topics = [];
    renderForm();
    renderPreview();
}

// Update the renderExistingTopics function to include a clear button
function renderExistingTopics() {
    const box = document.getElementById("topicsList");
    box.innerHTML = "";

    // Add clear editing button if we're currently editing
    if (topics.length > 0) {
        const clearDiv = document.createElement("div");
        clearDiv.style.padding = "10px";
        clearDiv.style.margin = "10px 0";
        clearDiv.style.border = "2px solid #f59e0b";
        clearDiv.style.backgroundColor = "#fffbeb";
        
        const editingText = document.createElement("strong");
        editingText.textContent = `Editing: ${topics[0].title}`;
        editingText.style.color = "#d97706";
        
        const clearBtn = document.createElement("button");
        clearBtn.textContent = "Cancel Editing";
        clearBtn.style.background = "#ef4444";
        clearBtn.style.color = "white";
        clearBtn.style.border = "none";
        clearBtn.style.padding = "4px 8px";
        clearBtn.style.borderRadius = "4px";
        clearBtn.style.marginLeft = "10px";
        clearBtn.style.cursor = "pointer";
        clearBtn.onclick = clearEditing;
        
        clearDiv.appendChild(editingText);
        clearDiv.appendChild(clearBtn);
        box.appendChild(clearDiv);
    }

    existingTopics.forEach(t => {
        const div = document.createElement("div");
        div.style.padding = "10px";
        div.style.margin = "5px 0";
        div.style.border = "1px solid #ddd";
        div.style.backgroundColor = topics[0] && topics[0].id === t.id ? "#f0f9ff" : "white";

        const titleSpan = document.createElement("strong");
        titleSpan.textContent = t.title;
        
        const editBtn = document.createElement("button");
        editBtn.className = "edit-btn";
        editBtn.textContent = topics[0] && topics[0].id === t.id ? "Currently Editing" : "Edit";
        editBtn.disabled = topics[0] && topics[0].id === t.id;
        editBtn.onclick = () => loadForEditing(t.id);

        div.appendChild(titleSpan);
        div.appendChild(editBtn);
        box.appendChild(div);
    });
}
// ---------------------------------------------------------
</script>

</body>
</html>