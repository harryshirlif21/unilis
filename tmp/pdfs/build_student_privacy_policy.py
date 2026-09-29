from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak

OUT = Path(__file__).resolve().parents[2] / "output" / "pdf" / "unilis-student-privacy-notice-kenya.pdf"
OUT.parent.mkdir(parents=True, exist_ok=True)

navy = colors.HexColor("#17345f")
gold = colors.HexColor("#c8a64b")
styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="CoverTitle", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=25, leading=31, textColor=navy, alignment=TA_CENTER, spaceAfter=10))
styles.add(ParagraphStyle(name="Deck", parent=styles["Normal"], fontSize=11, leading=16, alignment=TA_CENTER, textColor=colors.HexColor("#475569"), spaceAfter=16))
styles.add(ParagraphStyle(name="Section", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=13, leading=17, textColor=navy, spaceBefore=12, spaceAfter=5, keepWithNext=True))
styles.add(ParagraphStyle(name="BodyPolicy", parent=styles["BodyText"], fontSize=9.5, leading=14, textColor=colors.HexColor("#263449"), spaceAfter=6))
styles.add(ParagraphStyle(name="SmallPolicy", parent=styles["BodyText"], fontSize=8, leading=11, textColor=colors.HexColor("#475569"), spaceAfter=4))
styles.add(ParagraphStyle(name="Callout", parent=styles["BodyText"], fontSize=9.5, leading=14, textColor=navy, backColor=colors.HexColor("#eff6ff"), borderColor=colors.HexColor("#bfdbfe"), borderWidth=0.7, borderPadding=9, spaceBefore=8, spaceAfter=10))

def P(text, style="BodyPolicy"):
    return Paragraph(text, styles[style])

story = [Spacer(1, 12*mm), P("UNILIS", "CoverTitle"), P("STUDENT PRIVACY NOTICE", "CoverTitle"), P("Kenya | Student registration and use of the learning portal", "Deck")]
meta = Table([[P("Effective date", "SmallPolicy"), P("28 September 2026", "SmallPolicy"), P("Applies to", "SmallPolicy"), P("Student portal users", "SmallPolicy")]], colWidths=[28*mm, 42*mm, 25*mm, 52*mm])
meta.setStyle(TableStyle([("BACKGROUND", (0,0),(-1,-1),colors.HexColor("#f1f5f9")),("BOX",(0,0),(-1,-1),0.5,colors.HexColor("#cbd5e1")),("INNERGRID",(0,0),(-1,-1),0.3,colors.HexColor("#cbd5e1")),("VALIGN",(0,0),(-1,-1),"MIDDLE"),("LEFTPADDING",(0,0),(-1,-1),7),("TOPPADDING",(0,0),(-1,-1),7),("BOTTOMPADDING",(0,0),(-1,-1),7)]))
story += [meta, Spacer(1, 7*mm), P("This notice explains how personal data is handled when a student registers for or uses UNILIS, the JKUAT student learning portal. It is written for students in clear language and reflects the Kenyan data-protection framework in force as at the effective date.")]
story += [P("Who is responsible for your data?", "Section"), P("The student registration page identifies the portal as the JKUAT Student Portal. Jomo Kenyatta University of Agriculture and Technology (JKUAT) is therefore identified here as the institution responsible for student portal data. For privacy questions or rights requests, contact JKUAT through <link href='mailto:customercare@jkuat.ac.ke' color='#1d4ed8'>customercare@jkuat.ac.ke</link> or write to JKUAT, P.O. Box 62000-00200, Nairobi, Kenya. Ask for your request to be directed to the office responsible for data protection. JKUAT's published contact details are available at <link href='https://www.jkuat.ac.ke/contact-jkuat/' color='#1d4ed8'>jkuat.ac.ke/contact-jkuat</link>.")]
story += [P("Personal data we handle", "Section"), P("Depending on the features you use, the portal may handle: registration and identity details (name, registration number, email address and phone number); university affiliation and academic details (department, course, year of study, units, grades and progress); account and verification details (password hash, verification token and account status); learning activity (attendance, assessment answers, files you submit, and related feedback); and messages or support requests you send through portal features. The service may also create technical and security records such as login events and system logs. A password is stored in hashed form by the registration flow.")]
story += [P("Why and on what basis", "Section"), P("We use this information to create and secure student accounts, verify identity and eligibility, provide learning and student-administration services, manage courses and academic records, support teaching and assessment, communicate service notices, prevent misuse, maintain the portal, and meet applicable legal or institutional recordkeeping duties. Kenyan law requires processing to have a lawful basis. Depending on the specific activity, this may be performance of a public task or legal obligation, steps needed to provide a requested service, legitimate interests assessed against your rights, or consent where consent is the appropriate basis. Consent is not treated as the only basis for every operation of a university learning system.")]
story += [P("Sharing and transfers", "Section"), P("Access is limited to authorised university personnel and service providers who need data to operate, support, secure, or maintain the portal. Information may also be disclosed when required by law or a lawful order. UNILIS does not sell student personal data. If a service provider processes data outside Kenya, the University should ensure the transfer meets the Data Protection Act and applicable safeguards, and provide any required information to affected students. Specific integrations or specialist features may have additional notices.")]
story += [P("How long data is kept", "Section"), P("Student and academic records are retained according to applicable university and public-records retention requirements. Account, security, and service records are kept only as long as needed for their stated purposes, legal obligations, dispute handling, and security. When no longer needed, data should be securely deleted or irreversibly anonymised. The exact period can vary by record type and governing university schedule.")]
story += [P("Security", "Section"), P("JKUAT should apply appropriate technical and organisational safeguards, restrict access to authorised roles, and require service providers to protect information they handle. No online service can promise absolute security. If a personal-data breach creates the risks described by Kenyan law, the required notifications and protective steps should be taken.")]
story += [P("Your rights", "Section"), P("Subject to the Data Protection Act and any lawful limits, you may ask to be informed about use of your data, access it, correct inaccurate or incomplete information, request deletion where the law permits, object to processing, request restriction, or request portability where applicable. You may withdraw consent for processing that relies on consent; withdrawal does not make earlier lawful processing invalid and does not affect processing that has another lawful basis. Make a request using the JKUAT contact above and describe the account or record concerned. You may also complain to the Office of the Data Protection Commissioner (ODPC), Kenya, at <link href='https://www.odpc.go.ke/' color='#1d4ed8'>odpc.go.ke</link>.")]
story += [P("Children and specialist features", "Section"), P("The registration flow is intended for university students. Where a user is a child under Kenyan law, additional safeguards and any required parent or guardian involvement apply. Features that collect sensitive data, such as biometrics or health information, require appropriate safeguards and a separate explanation before collection; this general student notice does not itself authorise those collections.")]
story += [P("Changes to this notice", "Section"), P("We may update this notice when the portal, its data uses, or applicable law changes. The effective date above will be updated. Material changes should be communicated through an appropriate portal or university channel.")]
story += [P("Legal framework and official references", "Section"), P("This notice is informed by Article 31 of the Constitution of Kenya; the Data Protection Act, 2019 (now cited as Cap. 411C); the Data Protection (General) Regulations, 2021; and the ODPC Guidance Note for the Education Sector. These sources describe duties and rights; this notice is a portal-specific explanation, not a reproduction of the legislation.")]
story += [P("Official sources", "Section"), P("Office of the Data Protection Commissioner, Data Protection Laws in Kenya: <link href='https://www.odpc.go.ke/data-protection-laws-kenya/' color='#1d4ed8'>odpc.go.ke/data-protection-laws-kenya</link><br/>ODPC, Rights of a Data Subject: <link href='https://www.odpc.go.ke/rights-of-a-data-subject/' color='#1d4ed8'>odpc.go.ke/rights-of-a-data-subject</link><br/>ODPC, Guidance Note for the Education Sector: <link href='https://www.odpc.go.ke/wp-content/uploads/2024/02/ODPC-Guidance-Note-for-the-Education-Sector.pdf' color='#1d4ed8'>official guidance PDF</link><br/>Kenya Law, Data Protection Act (No. 24 of 2019): <link href='https://kenyalaw.org/kl/fileadmin/pdfdownloads/Acts/2019/TheDataProtectionAct__No24of2019.pdf' color='#1d4ed8'>official Act PDF</link>", "SmallPolicy")]
story += [P("This notice is prepared for the UNILIS student portal using the system information available. JKUAT should confirm the responsible office, service-provider list, international hosting locations, record-retention schedule, and operational safeguards before treating it as its final institutional privacy policy.", "Callout")]

def footer(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setStrokeColor(gold)
    canvas.setLineWidth(1.2)
    canvas.line(18*mm, 16*mm, w-18*mm, 16*mm)
    canvas.setFont("Helvetica", 8)
    canvas.setFillColor(colors.HexColor("#64748b"))
    canvas.drawString(18*mm, 10*mm, "UNILIS Student Privacy Notice | Kenya")
    canvas.drawRightString(w-18*mm, 10*mm, f"Page {doc.page}")
    canvas.restoreState()

doc = SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=20*mm, leftMargin=20*mm, topMargin=18*mm, bottomMargin=23*mm, title="UNILIS Student Privacy Notice - Kenya", author="UNILIS")
doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT)
