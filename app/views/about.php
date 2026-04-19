<?php
// about.php - About Us page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- About Page Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="display-4 fw-bold mb-4">About <span class="text-primary">Us</span></h1>
                <p class="lead">Learn more about our custom furniture company and our commitment to excellence.</p>
                
                <div class="row mt-5">
                    <div class="col-md-6">
                        <!-- About Us Slideshow -->
                        <div style="position:relative;width:100%;height:380px;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.15);" id="aboutSlideshow">
                            <?php
                            $aboutImgs = ['about1.jpg','about2.jpg','about3.jpg','about4.jpg','about5.jpg','about6.jpg','about7.jpg','about8.jpg'];
                            foreach ($aboutImgs as $i => $img):
                            ?>
                            <div class="about-slide" style="position:absolute;top:0;left:0;width:100%;height:100%;opacity:<?php echo $i===0?'1':'0'; ?>;transition:opacity 0.8s ease-in-out;">
                                <img src="<?php echo BASE_URL; ?>/public/assets/images/about/<?php echo $img; ?>"
                                     alt="Workshop <?php echo $i+1; ?>"
                                     style="width:100%;height:100%;object-fit:cover;"
                                     onerror="this.closest('.about-slide').style.display='none'">
                            </div>
                            <?php endforeach; ?>
                            <button onclick="aboutPrev()" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.8);border:none;border-radius:50%;width:36px;height:36px;font-size:16px;cursor:pointer;z-index:10;">&#8249;</button>
                            <button onclick="aboutNext()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.8);border:none;border-radius:50%;width:36px;height:36px;font-size:16px;cursor:pointer;z-index:10;">&#8250;</button>
                            <div style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:6px;z-index:10;" id="aboutDots">
                                <?php foreach ($aboutImgs as $i => $img): ?>
                                <span onclick="aboutGoTo(<?php echo $i; ?>)" style="width:8px;height:8px;border-radius:50%;background:<?php echo $i===0?'white':'rgba(255,255,255,0.5)'; ?>;cursor:pointer;transition:background .3s;" class="about-dot"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <script>
                        (function(){
                            const slides = document.querySelectorAll('.about-slide');
                            const dots   = document.querySelectorAll('.about-dot');
                            let cur = 0, timer;
                            function show(n) {
                                slides[cur].style.opacity='0'; dots[cur].style.background='rgba(255,255,255,0.5)';
                                cur = (n + slides.length) % slides.length;
                                slides[cur].style.opacity='1'; dots[cur].style.background='white';
                            }
                            function start() { timer = setInterval(()=>show(cur+1), 3500); }
                            function reset() { clearInterval(timer); start(); }
                            window.aboutNext = function(){ show(cur+1); reset(); };
                            window.aboutPrev = function(){ show(cur-1); reset(); };
                            window.aboutGoTo = function(n){ show(n); reset(); };
                            start();
                        })();
                        </script>
                    </div>
                    <div class="col-md-6">
                        <h3>Our Story</h3>
                        <p>Founded in 2015, Custom Furniture ERP began as a small workshop with a passion for creating beautiful, functional furniture. Today, we've grown into a leading provider of custom furniture solutions, serving clients nationwide.</p>
                        
                        <h3>Our Mission</h3>
                        <p>We believe that every piece of furniture tells a story. Our mission is to craft unique, sustainable, and beautiful pieces that enhance your living spaces and reflect your personal style.</p>
                        
                        <h3>Our Values</h3>
                        <ul>
                            <li>Quality craftsmanship</li>
                            <li>Environmental responsibility</li>
                            <li>Exceptional customer service</li>
                            <li>Innovation in design</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
</body>
</html>