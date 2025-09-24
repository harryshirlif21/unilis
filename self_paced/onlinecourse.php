<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Certifications</title>
    <style>
        /* CSS with a golden theme and light nav bar */
        body {
            font-family: 'Times New Roman', serif;
            margin: 0;
            padding: 0;
            background-color: #fdf7e3; /* Light cream background */
        }
        .container {
            width: 90%;
            margin: 0 auto;
        }
        nav {
            background-color: #e6d6b2; /* Light cream nav bar */
            color: #4a3f2d; /* Dark brown nav text */
            padding: 1rem 0;
            text-align: center;
        }
        nav .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }
        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        nav ul li {
            margin: 0 15px;
        }
        nav ul li a {
            color: #4a3f2d; /* Dark brown link color */
            text-decoration: none;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        nav ul li a:hover {
            background-color: #c2b078; /* Muted gold on hover */
            color: #fff;
        }
        .courses-section {
            padding: 2rem 0;
        }
        .courses-section h1 {
            text-align: center;
            color: #4a3f2d; /* Dark brown heading color */
            margin-bottom: 2rem;
        }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .course-card {
            background-color: #fefefe; /* Off-white card background */
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .course-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .course-info {
            padding: 1rem;
        }
        .course-info h3 {
            margin-top: 0;
            color: #362e15; /* Warm black/brown heading text */
        }
        .course-info p {
            color: #5d4f3b; /* Medium brown text color */
            font-size: 0.9rem;
        }
        .course-info .price {
            font-weight: bold;
            color: #a1884e; /* Golden price color */
            font-size: 1.1rem;
        }

        /* Carousel styles */
        .carousel-section {
            padding: 2rem 0;
            background-color: #e6d6b2;
            position: relative;
        }
        .carousel-container {
            display: flex;
            overflow-x: scroll;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding: 1rem;
            gap: 20px;
        }
        .carousel-container::-webkit-scrollbar {
            display: none;
        }
        .carousel-container .course-card {
            min-width: 300px;
        }
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
            pointer-events: none; /* Allows clicks to pass through to the cards */
        }
        .carousel-nav button {
            pointer-events: all; /* Re-enables clicks for the buttons */
            background-color: rgba(74, 63, 45, 0.7);
            color: #fff;
            border: none;
            padding: 10px 15px;
            font-size: 1.5rem;
            cursor: pointer;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        .carousel-nav button:hover {
            background-color: #4a3f2d;
        }
    </style>
</head>
<body>

    <header>
        <nav>
            <div class="container">
                <span class="logo">Online Certifications</span>
                <ul>
                    <li><a href="#it">IT & Software</a></li>
                    <li><a href="#business">Business</a></li>
                    <li><a href="#design">Design</a></li>
                    <li><a href="#marketing">Marketing</a></li>
                    <li><a href="#language">Language</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <section class="carousel-section">
            <div class="container">
                <h1>Featured Certifications 🔥</h1>
                <div class="carousel-container" id="carousel">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/a1884e/4a3f2d?text=Full-Stack+Dev" alt="Full-Stack Development Course">
                        <div class="course-info">
                            <h3>Full-Stack Development</h3>
                            <p>Build complete web applications with front-end and back-end technologies.</p>
                            <span class="price">$149.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/a1884e/4a3f2d?text=UX/UI+Design" alt="UX/UI Design Course">
                        <div class="course-info">
                            <h3>UX/UI Design for Beginners</h3>
                            <p>Learn to create intuitive and beautiful user experiences.</p>
                            <span class="price">$69.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/a1884e/4a3f2d?text=Agile+Project+Mgmt" alt="Agile Project Management Course">
                        <div class="course-info">
                            <h3>Agile Project Management</h3>
                            <p>Master the Scrum framework and lead successful agile teams.</p>
                            <span class="price">$89.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/a1884e/4a3f2d?text=Digital+Marketing" alt="Digital Marketing Course">
                        <div class="course-info">
                            <h3>Digital Marketing Essentials</h3>
                            <p>Get certified in SEO, SEM, social media, and content marketing.</p>
                            <span class="price">$59.99</span>
                        </div>
                    </div>
                </div>
                <div class="carousel-nav">
                    <button id="prevBtn">‹</button>
                    <button id="nextBtn">›</button>
                </div>
            </div>
        </section>

        <div class="container">
            <section id="it" class="courses-section">
                <h1>IT & Software Certifications 💻</h1>
                <div class="course-grid">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Web+Dev" alt="Web Development Course">
                        <div class="course-info">
                            <h3>Web Development Fundamentals</h3>
                            <p>Learn the basics of HTML, CSS, and JavaScript to build responsive websites.</p>
                            <span class="price">$49.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Data+Science" alt="Data Science Course">
                        <div class="course-info">
                            <h3>Introduction to Data Science</h3>
                            <p>An introduction to data analysis, machine learning, and data visualization.</p>
                            <span class="price">$79.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Cybersecurity" alt="Cybersecurity Course">
                        <div class="course-info">
                            <h3>Cybersecurity Essentials</h3>
                            <p>Protect yourself and your organization from common cyber threats.</p>
                            <span class="price">$69.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Python+Programming" alt="Python Programming Course">
                        <div class="course-info">
                            <h3>Python Programming for AI</h3>
                            <p>Master Python to build and deploy artificial intelligence models.</p>
                            <span class="price">$89.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Cloud+Computing" alt="Cloud Computing Course">
                        <div class="course-info">
                            <h3>Cloud Computing Fundamentals</h3>
                            <p>Explore the principles of cloud platforms like AWS and Google Cloud.</p>
                            <span class="price">$119.99</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="business" class="courses-section">
                <h1>Business Certifications 📈</h1>
                <div class="course-grid">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Project+Mgmt" alt="Project Management Course">
                        <div class="course-info">
                            <h3>Project Management Professional (PMP)</h3>
                            <p>Master project planning, execution, and risk management.</p>
                            <span class="price">$99.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Financial+Planning" alt="Financial Planning Course">
                        <div class="course-info">
                            <h3>Certified Financial Planner</h3>
                            <p>Gain the skills to manage personal and corporate finances.</p>
                            <span class="price">$129.99</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="design" class="courses-section">
                <h1>Design Certifications 🎨</h1>
                <div class="course-grid">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Design" alt="Graphic Design Course">
                        <div class="course-info">
                            <h3>Graphic Design Masterclass</h3>
                            <p>Explore tools and principles of professional graphic design.</p>
                            <span class="price">$59.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=UI/UX" alt="UI/UX Design Course">
                        <div class="course-info">
                            <h3>UI/UX Design Certificate</h3>
                            <p>Learn to design user-friendly interfaces with a focus on user experience.</p>
                            <span class="price">$79.99</span>
                        </div>
                    </div>
                </div>
            </section>
            
            <section id="marketing" class="courses-section">
                <h1>Marketing Certifications 📊</h1>
                <div class="course-grid">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=SEO+Marketing" alt="SEO Marketing Course">
                        <div class="course-info">
                            <h3>Search Engine Optimization (SEO)</h3>
                            <p>Drive organic traffic and improve your website's search ranking.</p>
                            <span class="price">$49.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Content+Marketing" alt="Content Marketing Course">
                        <div class="course-info">
                            <h3>Content Marketing Strategy</h3>
                            <p>Create and execute a compelling content strategy to engage your audience.</p>
                            <span class="price">$59.99</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="language" class="courses-section">
                <h1>Language Certifications 🗣️</h1>
                <div class="course-grid">
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=Spanish" alt="Spanish Language Course">
                        <div class="course-info">
                            <h3>Spanish Fluency Certificate</h3>
                            <p>Master conversational Spanish and open up a new world of opportunities.</p>
                            <span class="price">$39.99</span>
                        </div>
                    </div>
                    <div class="course-card">
                        <img src="https://placehold.co/400x200/4a3f2d/c2b078?text=French" alt="French Language Course">
                        <div class="course-info">
                            <h3>French for Travelers</h3>
                            <p>Learn essential phrases and cultural norms for your next trip to France.</p>
                            <span class="price">$34.99</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const carousel = document.getElementById('carousel');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const scrollDistance = 320; // Width of a card + gap

            prevBtn.addEventListener('click', () => {
                carousel.scrollLeft -= scrollDistance;
            });

            nextBtn.addEventListener('click', () => {
                carousel.scrollLeft += scrollDistance;
            });
        });
    </script>
</body>
</html>