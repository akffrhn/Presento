<?php
session_start();
include('navbar.php');  
include('dbcon.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>CYCOM E-Proposal Event System</title>
</head>

<body class="index-page">

  <!-- navbar.php already rendered the <header> above -->

  <main class="main">

    <!-- Hero Section -->
    <section id="hero" class="hero section" style="position:relative; overflow:hidden; min-height:100%;">

      <!-- Background Swiper -->
     <!-- Background Swiper -->
<div class="swiper init-swiper"
     style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:1;">

  <script type="application/json" class="swiper-config">
  {
    "loop": true,
    "speed": 600,
    "autoplay": {
      "delay": 5000
    },
    "slidesPerView": 1,
    "pagination": {
      "el": ".swiper-pagination",
      "type": "bullets",
      "clickable": true
    }
  }
  </script>

  <div class="swiper-wrapper">

    <div class="swiper-slide">
      <img src="/Presento/assets/img/run2.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

    <div class="swiper-slide">
      <img src="/Presento/assets/img/run1.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

    <div class="swiper-slide">
      <img src="/Presento/assets/img/cycom-bg.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

      <div class="swiper-slide">
      <img src="/Presento/assets/img/jomasukcs1.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

    <div class="swiper-slide">
      <img src="/Presento/assets/img/jomasukcs2.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

    <div class="swiper-slide">
      <img src="/Presento/assets/img/jomasukcs3.JPG"
           alt=""
           style="width:100%; height:100%; object-fit:cover;">
    </div>

  </div>

  <!-- Pagination -->
  <div class="swiper-pagination" style="z-index:3;"></div>

</div>

      <!-- Dark overlay -->
      <div style="position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0); z-index:2;"></div>

      <!-- Text content -->
      <div class="container" style="position:relative; z-index:3; padding-top:150px; padding-bottom:100px;">
        <div class="row">
          <div class="col-lg-6">
            <h2 style="color:#fff;" data-aos="fade-up" data-aos-delay="100">Think you have a better idea for an event for Computer Science Student?</h2>
            <p style="color:#fff;" data-aos="fade-up" data-aos-delay="200">Tell us about it!</p>
            <div class="d-flex mt-4" data-aos="fade-up" data-aos-delay="300">
              <a href="#about" class="btn-get-started">Submit your dream event</a>
            </div>
          </div>
        </div>
      </div>

    </section><!-- /Hero Section -->

    <!-- About Section -->
    <section id="about" class="about section section-bg dark-background">
      <div class="container position-relative">
        <div class="row gy-5">

          <div class="content col-xl-5 d-flex flex-column" data-aos="fade-up" data-aos-delay="100">
            <h3>What is CYCOM?</h3>
            <p>
              The official student club representing all Diploma in Computer Science students at
              Universiti Teknologi MARA (UiTM) Kedah Branch, located in Merbok, Kedah, Malaysia.
            </p>
            <a href="#" class="about-btn align-self-center align-self-xl-start">
              <span>About us</span> <i class="bi bi-chevron-right"></i>
            </a>
          </div>

          <div class="col-xl-7" data-aos="fade-up" data-aos-delay="200">
            <div class="row gy-4">
              <div class="col-md-6 icon-box position-relative">
                <i class="bi bi-briefcase"></i>
                <h4><a href="" class="stretched-link">Our Mission</a></h4>
                <p>To bridge the gap between formal education and the broader skills required for student life and professional readiness.</p>
              </div>
              <div class="col-md-6 icon-box position-relative">
                <i class="bi bi-gem"></i>
                <h4><a href="" class="stretched-link">Our Vision</a></h4>
                <p>To be a holistic and progressive academic club developing well-rounded Computer Science students who excel academically, in sports, and in personal well-being.</p>
              </div>
              <div class="col-md-6 icon-box position-relative">
                <i class="bi bi-broadcast"></i>
                <h4><a href="" class="stretched-link">Our Values</a></h4>
                <p>Creating a dynamic community where students can thrive academically, build strong character, and contribute meaningfully to campus life and wider society.</p>
              </div>
              <div class="col-md-6 icon-box position-relative">
                <i class="bi bi-easel"></i>
                <h4><a href="" class="stretched-link">Our Aspiration</a></h4>
                <p>To be a leading example within the university by encouraging a balanced student lifestyle that values knowledge, physical health, and compassion.</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section><!-- /About Section -->

  </main>
<?php include('footer.php'); ?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>  
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
  <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>

  <!-- Main JS File -->
 
   <script src="assets/js/main.js"></script>

</body>
</html>