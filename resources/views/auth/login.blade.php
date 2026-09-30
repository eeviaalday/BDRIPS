<!DOCTYPE html>
{{--
    The photograph is set on the html element rather than in the stylesheet
    below, for two reasons.

    First, it is what actually makes the photograph appear. --photo is declared
    in :root as a gradient stand-in, and body paints background:var(--photo)
    center/cover fixed - so without this override the login page renders that
    flat gradient and the photograph never shows, even though the body rule, the
    navy overlay on .left and the frosted sheet on .right are all already
    correct. An inline custom property on the element wins over the same
    property in :root, which is how register.blade.php has always done it, and
    this is the whole difference between the two pages' backgrounds.

    Second, asset() is a Blade call. It cannot live in the stylesheet, which is
    static text. The gradient stays in :root as the fallback for when the
    photograph is missing, so the page degrades to a plain panel rather than
    breaking.

    It has to be the same photograph register uses, or the two pages will not
    read as one design when a resident moves between them.
--}}
<html lang="en" style="--photo: url('{{ asset('images/login.jpg') }}')">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Log in · Barangay Linasin</title>
<style>
/*
  Figtree is self-hosted, exactly as it is on every other page.

  This page used to pull the family from the Google Fonts CDN, which made it
  the one screen in the application whose typography depended on the network:
  with the CDN blocked, unreachable or slow it fell back to system-ui, and the
  login looked nothing like the dashboard a resident was about to land on. The
  woff2 files are already in public/fonts, so the same @font-face declarations
  as resources/css/app.css are repeated here. Duplication is the deliberate
  cost of this page staying a standalone document - it is the only view in the
  portal that does not go through the Vite bundle.

  font-display:swap keeps the text readable while the file loads, and the system
  stack in the body rule is what renders if the font never arrives at all.
*/
@font-face{font-family:'Figtree';font-style:normal;font-weight:400;font-display:swap;src:url('/fonts/figtree-latin-400.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Figtree';font-style:normal;font-weight:400;font-display:swap;src:url('/fonts/figtree-latin-ext-400.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Figtree';font-style:normal;font-weight:500;font-display:swap;src:url('/fonts/figtree-latin-500.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Figtree';font-style:normal;font-weight:500;font-display:swap;src:url('/fonts/figtree-latin-ext-500.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Figtree';font-style:normal;font-weight:600;font-display:swap;src:url('/fonts/figtree-latin-600.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Figtree';font-style:normal;font-weight:600;font-display:swap;src:url('/fonts/figtree-latin-ext-600.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Figtree';font-style:normal;font-weight:700;font-display:swap;src:url('/fonts/figtree-latin-700.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Figtree';font-style:normal;font-weight:700;font-display:swap;src:url('/fonts/figtree-latin-ext-700.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}
@font-face{font-family:'Figtree';font-style:normal;font-weight:800;font-display:swap;src:url('/fonts/figtree-latin-800.woff2') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Figtree';font-style:normal;font-weight:800;font-display:swap;src:url('/fonts/figtree-latin-ext-800.woff2') format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF}

:root{
  /* Fallback only. The photograph itself is set on the <html> element above,
     because that is the only place a Blade asset() call can be used. */
  --photo:linear-gradient(180deg,#5a86c0 0%,#9dbbe0 30%,#4d7358 58%,#1d3a2b 100%);
  --navy:#0c2240; --gold:#f5b82e; --goldink:#3a2900;
  --glass:rgba(255,255,255,.72); --ink:#15294a; --muted:#43536e; --line:rgba(21,41,74,.18); --field:rgba(255,255,255,.75); --blue:#2b5aa8;
  --ribbon:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 800' preserveAspectRatio='none'><path d='M60 0C100 50 100 150 60 200C20 250 20 350 60 400C100 450 100 550 60 600C20 650 20 750 60 800' fill='none' stroke='black' stroke-width='10'/></svg>") 0 0/120px 100% no-repeat;
  --wave:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 800' preserveAspectRatio='none'><path d='M60 0C100 50 100 150 60 200C20 250 20 350 60 400C100 450 100 550 60 600C20 650 20 750 60 800L120 800L120 0Z'/></svg>");
  --mask:var(--wave) 0 0/120px 100% no-repeat, linear-gradient(#000,#000) 119px 0/calc(100% - 119px) 100% no-repeat;
  box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--glass:rgba(14,24,42,.86);--ink:#e8eefb;--muted:#a3b2cc;--line:rgba(232,238,251,.2);--field:rgba(255,255,255,.06);--blue:#8db1f2}}
:root[data-theme="dark"]{--glass:rgba(14,24,42,.86);--ink:#e8eefb;--muted:#a3b2cc;--line:rgba(232,238,251,.2);--field:rgba(255,255,255,.06);--blue:#8db1f2}
*{box-sizing:border-box}
html{scroll-padding-top:env(safe-area-inset-top,0px)}
body{margin:0;min-height:100vh;font-family:Figtree,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);line-height:1.5;background:var(--photo) center/cover fixed}
:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
a{color:var(--blue);font-weight:600}

.page{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);position:relative;overflow:hidden}

.left{position:relative;z-index:1;color:#fff;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:56px 150px 56px 48px;
  background:linear-gradient(180deg,rgba(12,34,64,.62),rgba(12,34,64,.8));backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)}
.home{position:absolute;top:24px;left:28px;color:#fff;font-size:14px;font-weight:600;text-decoration:none;opacity:.9}
.home:hover{text-decoration:underline}
.seal{width:92px;height:92px;border-radius:50%;border:4px solid rgba(255,255,255,.45);box-shadow:0 10px 30px rgba(0,0,0,.35)}
.left h2{margin:20px 0 2px;font-size:28px;letter-spacing:.14em;font-weight:800}
.zone{opacity:.85;font-size:15px}
.rule{width:56px;height:3px;background:var(--gold);border-radius:3px;margin:24px auto}
.left h1{margin:0 0 8px;font-size:38px;letter-spacing:-.02em;line-height:1.1}
.left p{margin:0;max-width:340px;opacity:.92}
.perks{list-style:none;margin:28px 0 0;padding:0;display:grid;gap:12px;text-align:left}
.perks li{display:flex;gap:12px;align-items:center;font-size:15px}
.perks span{flex:none;width:34px;height:34px;border-radius:10px;background:rgba(245,184,46,.2);color:var(--gold);display:grid;place-items:center}

.right{position:relative;margin-left:-120px;z-index:2;display:grid;place-items:center;padding:48px 40px 48px 170px}
.right::before,.right::after{content:"";position:absolute;top:0;bottom:0;z-index:-1}
.right::before{left:-14px;width:120px;background:var(--gold);-webkit-mask:var(--ribbon);mask:var(--ribbon)}
.right::after{left:0;right:0;-webkit-mask:var(--mask);mask:var(--mask);background:var(--glass);backdrop-filter:blur(18px) saturate(1.2);-webkit-backdrop-filter:blur(18px) saturate(1.2)}
.form{width:100%;max-width:420px}
.form h3{margin:0;font-size:30px;letter-spacing:-.01em}
.sub{margin:4px 0 26px;color:var(--muted)}
.field{margin-bottom:18px}
label{display:block;font-size:14px;font-weight:600;margin-bottom:6px}
.wrap{position:relative}
.wrap>svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}
.f{width:100%;height:50px;padding:0 44px;border:1.5px solid var(--line);border-radius:12px;background:var(--field);color:var(--ink);font:inherit;font-size:15px;transition:border-color .15s,box-shadow .15s}
.f::placeholder{color:var(--muted);opacity:.8}
.f:hover{border-color:var(--blue)}
.f:focus{outline:0;border-color:var(--blue);box-shadow:0 0 0 4px rgba(43,90,168,.22)}
.eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;background:transparent;color:var(--muted);border-radius:8px;cursor:pointer;display:grid;place-items:center}
.eye:hover{color:var(--ink);background:rgba(128,128,128,.14)}
.row{display:flex;justify-content:space-between;align-items:center;margin:2px 0 22px;font-size:14px}
.row label{display:flex;gap:8px;align-items:center;margin:0;font-weight:500;cursor:pointer}
.row input{width:18px;height:18px;accent-color:var(--navy)}
.btn{width:100%;height:52px;border:0;border-radius:12px;background:var(--gold);color:var(--goldink);font:inherit;font-weight:700;font-size:16px;cursor:pointer;transition:transform .15s,box-shadow .15s}
.btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(245,184,46,.45)}
.or{display:flex;align-items:center;gap:12px;margin:22px 0 14px;color:var(--muted);font-size:13px}
.or::before,.or::after{content:"";flex:1;height:1px;background:var(--line)}
.reg{display:block;text-align:center;height:48px;line-height:45px;border:1.5px solid var(--line);border-radius:12px;color:var(--ink);text-decoration:none;font-weight:600;transition:border-color .15s,background .15s}
.reg:hover{border-color:var(--blue);background:var(--field)}
.priv{display:flex;gap:10px;margin:22px 0 0;padding-top:16px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
.priv svg{flex:none;margin-top:2px}

@media(max-width:860px){
  .page{grid-template-columns:1fr}
  .left{padding:64px 24px 56px}
  .perks{display:none}.left h1{font-size:30px}.rule{margin:16px auto}
  .right{margin:-28px 0 0;padding:34px 22px 40px;border-radius:28px 28px 0 0}
  .right::before,.right::after{-webkit-mask:none;mask:none;border-radius:28px 28px 0 0}
  .right::before{left:0;right:0;width:auto;top:-6px;background:rgba(245,184,46,.85)}
}
</style>
</head>
<body>
<div class="page">
  <section class="left">
    <a class="home" href="/">&larr; Back to home</a>
    {{-- The real barangay seal, not the design's gold placeholder circle. The
         .seal rule still supplies the 92px round frame, the white ring and the
         drop shadow. alt="" because the wordmark sits directly below it and
         would repeat the name otherwise. --}}
    <img class="seal" src="{{ asset('images/Zone 1_logo.png') }}" alt="" width="92" height="92">
    <h2>BARANGAY LINASIN</h2>
    <div class="zone">Zone 1, Bangued, Abra</div>
    <div class="rule"></div>
    <h1>Welcome back!</h1>
    <p>Sign in to access your dashboard and barangay services.</p>
    <ul class="perks">
      <li><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg></span>Request certificates and clearances</li>
      <li><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>Track the status of your requests</li>
      <li><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg></span>Get community announcements</li>
    </ul>
  </section>

  <section class="right">
    <form class="form" method="POST" action="{{ route('login') }}">
      @csrf
      <h3>Sign in</h3>
      <p class="sub">Enter your details to continue.</p>

      <div class="field">
        <label for="email">Email address</label>
        <div class="wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <input class="f" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required autofocus>
        </div>
      </div>

      <div class="field">
        <label for="pw">Password</label>
        <div class="wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          <input class="f" id="pw" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
          <button class="eye" type="button" id="eye" aria-label="Show password"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:static;transform:none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
      </div>

      <div class="row">
        <label><input type="checkbox" name="remember"> Remember me</label>
        @if (Route::has('password.request'))
          <a href="{{ route('password.request') }}">Forgot password?</a>
        @endif
      </div>

      <button class="btn" type="submit">Log in</button>

      <div class="or">New to the barangay portal?</div>
      @if (Route::has('register'))
        <a class="reg" href="{{ route('register') }}">Create an account</a>
      @endif

      <p class="priv"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/></svg><span>Your information is used solely for official barangay services under the Data Privacy Act of 2012 (R.A. 10173).</span></p>
    </form>
  </section>
</div>

<script>
var pw=document.getElementById('pw'),eye=document.getElementById('eye');
eye.addEventListener('click',function(){var s=pw.type==='password';pw.type=s?'text':'password';eye.setAttribute('aria-label',s?'Hide password':'Show password');});
</script>
</body>
</html>
