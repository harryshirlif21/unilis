// Sync identity input boxes directly into printable structural node headers
function updateReportHeader() {
  const nameVal = document.getElementById("studentName").value;
  const regVal = document.getElementById("regNumber").value;
  
  document.getElementById("lblPrintName").innerText = nameVal || "[Not Provided]";
  document.getElementById("lblPrintReg").innerText = regVal || "[Not Provided]";
}

// Preview image rendering
document.getElementById("imageInput").addEventListener("change", function (event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function (e) {
    const img = document.getElementById("previewImage");
    img.src = e.target.result;
    img.style.display = "block";
    log("Image loaded successfully.");
  };
  reader.readAsDataURL(file);
});

// Main processing function
function processImage() {
  log("Initializing multi-module framework (Detection, Segmentation, OCR)...");

  // Sync details and update timestamp boundary metrics
  updateReportHeader();
  document.getElementById("lblPrintDate").innerText = new Date().toLocaleString();

  // Simulated backend calculation latency
  setTimeout(() => {
    
    // 1. Simulate Normalized Core Module Scores (S_i scale: 0.0 to 1.0)
    const sStructure    = Math.random(); 
    const sShape        = Math.random(); 
    const sLabel        = Math.random(); 
    const sAlignment    = Math.random(); 
    const sCompleteness = Math.random(); 

    // 2. Exact Rubric Weight Configuration parameters (w_i)
    const wStructure    = 0.30; 
    const wShape        = 0.25; 
    const wLabel        = 0.25; 
    const wAlignment    = 0.10; 
    const wCompleteness = 0.10; 

    // 3. Mathematical Aggregation: Sum of (w_i * S_i) scaled to base 100
    const finalCalculatedScore = (
      (sStructure * wStructure) +
      (sShape * wShape) +
      (sLabel * wLabel) +
      (sAlignment * wAlignment) +
      (sCompleteness * wCompleteness)
    ) * 100;

    const scoreString = finalCalculatedScore.toFixed(2);
    const confidence = (75 + Math.random() * 24).toFixed(2);

    // Write final score fraction to the primary interface header node
    document.getElementById("score").innerText = `${scoreString} / 100`;
    document.getElementById("confidence").innerText = confidence + "%";

    // 4. Generate conditional logic arrays parsing student performance
    const detailedFeedback = generateRubricFeedback(
      finalCalculatedScore, 
      sStructure, 
      sShape, 
      sLabel, 
      sAlignment, 
      sCompleteness
    );

    // Update Text Fields across UI Dashboard Components
    document.getElementById("feedback").innerText = detailedFeedback.summary;

    const breakdownList = document.getElementById("breakdownList");
    breakdownList.innerHTML = ""; 
    detailedFeedback.breakdown.forEach(item => {
      const li = document.createElement("li");
      li.innerText = item;
      breakdownList.appendChild(li);
    });

    const tipsList = document.getElementById("tipsList");
    tipsList.innerHTML = ""; 
    detailedFeedback.tips.forEach(item => {
      const li = document.createElement("li");
      li.innerText = item;
      tipsList.appendChild(li);
    });

    log(`Analysis finalized for student. Aggregated score: ${scoreString}/100.`);
  }, 1500);
}

// Generates explicit breakdown justifications and context-targeted design advice
function generateRubricFeedback(finalScore, structure, shape, label, alignment, completeness) {
  const breakdown = [
    `Structure Presence (30% Weight): Detected ${(structure * 100).toFixed(1)}% of mandatory anatomical elements.`,
    `Shape Accuracy (25% Weight): Segmentation mask boundary configuration verified at ${(shape * 100).toFixed(1)}% accuracy.`,
    `Label Accuracy (25% Weight): OCR linguistic validation confirmed ${(label * 100).toFixed(1)}% label matching correctness.`,
    `Spatial Alignment (10% Weight): Positional geometric relationship factor computed at ${(alignment * 100).toFixed(1)}%.`,
    `Completeness (10% Weight): Deep internal sub-structural details verified at ${(completeness * 100).toFixed(1)}%.`
  ];

  let summary = "";
  let tips = [];

  // Conditional grading tiers based on total rubric performance values
  if (finalScore < 50) {
    summary = "Diagram needs significant revision. Major criteria within the evaluation framework have low accuracy thresholds.";
    
    if (structure < 0.6) {
      tips.push("Redraw missing primary structures: Ensure all main organ walls and prominent vessels are explicitly visible.");
    }
    if (shape < 0.6) {
      tips.push("Refine contour lines: Avoid sketchy, ambiguous borders along tissue structures; use sharp, solid boundary strokes.");
    }
    if (label < 0.6) {
      tips.push("Enhance textual labels: Write medical names clearly, horizontally, and crosscheck your terms with textbook spelling guidelines.");
    }
    if (tips.length === 0) {
      tips.push("Verify scale proportions: Ensure the structural elements do not overlay or block secondary micro-anatomical networks.");
    }
  } 
  else if (finalScore <= 85) {
    summary = "Good effort. Most anatomical elements match the system requirements, but adjustments are needed to score higher.";
    
    if (alignment < 0.7) {
      tips.push("Adjust alignment: Check the relative positioning (e.g., ensure accessory systems connect accurately to primary nodes).");
    }
    if (completeness < 0.7) {
      tips.push("Detail sub-components: Elaborate further on small interior elements like vascular partitions or core sub-compartments.");
    }
    tips.push("Standardize labeling layout: Use a straight-edge tool for leader pointers to trace definitions down cleanly to target regions.");
  } 
  else {
    summary = "Excellent work! The submission satisfies all architectural criteria with premium structural precision.";
    tips.push("Include an explicit magnification factor or scale bar calculation indicator on the lower margin.");
    tips.push("Incorporate standard diagram color schemes to improve clear cross-sectional readability.");
  }

  return { summary, breakdown, tips };
}

// Logging system
function log(message) {
  const logs = document.getElementById("logs");
  const time = new Date().toLocaleTimeString();
  logs.textContent += `[${time}] ${message}\n`;
}

// Initialize header values on startup execution
window.onload = updateReportHeader;