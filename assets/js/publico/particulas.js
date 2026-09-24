/* Fondo animado de las pantallas públicas (login y recuperación). */
(function() {
  var canvas = document.getElementById('particle-canvas');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var W, H, nodes = [], mouse = { x: -999, y: -999 }, animId;

  function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function mkNode() {
    return {
      x: Math.random() * W,
      y: Math.random() * H,
      vx: (Math.random() - 0.5) * 0.3,
      vy: (Math.random() - 0.5) * 0.3,
      r: Math.random() * 1.5 + 0.4,
      phi: Math.random() * Math.PI * 2
    };
  }

  function initNodes() {
    nodes = [];
    var count = Math.min(70, Math.floor(W * H / 18000));
    for (var i = 0; i < count; i++) nodes.push(mkNode());
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    var maxD = 140, mouseD = 160;

    for (var i = 0; i < nodes.length; i++) {
      for (var j = i + 1; j < nodes.length; j++) {
        var dx = nodes[i].x - nodes[j].x;
        var dy = nodes[i].y - nodes[j].y;
        var d = Math.hypot(dx, dy);
        if (d < maxD) {
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = 'rgba(52,211,153,' + ((1 - d / maxD) * 0.2) + ')';
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    }

    for (var k = 0; k < nodes.length; k++) {
      var n = nodes[k];
      n.phi += 0.01;
      var glow = Math.sin(n.phi) * 0.3 + 0.5;

      var mdx = n.x - mouse.x, mdy = n.y - mouse.y;
      var md = Math.hypot(mdx, mdy);
      if (md < mouseD && md > 0) {
        var force = (1 - md / mouseD) * 0.4;
        n.vx += (mdx / md) * force;
        n.vy += (mdy / md) * force;
      }
      n.vx *= 0.97;
      n.vy *= 0.97;

      ctx.beginPath();
      var radius = n.r * (md < mouseD ? 1 + (1 - md / mouseD) * 0.8 : 1);
      ctx.arc(n.x, n.y, radius, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(52,211,153,' + glow + ')';
      ctx.shadowBlur = md < mouseD ? 12 : 6;
      ctx.shadowColor = 'rgba(52,211,153,0.4)';
      ctx.fill();
      ctx.shadowBlur = 0;

      n.x += n.vx;
      n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    }

    animId = requestAnimationFrame(draw);
  }

  window.addEventListener('mousemove', function(e) { mouse.x = e.clientX; mouse.y = e.clientY; });
  window.addEventListener('mouseleave', function() { mouse.x = -999; mouse.y = -999; });

  window.addEventListener('click', function(e) {
    for (var i = 0; i < 4; i++) {
      var n = mkNode();
      n.x = e.clientX; n.y = e.clientY;
      var angle = (Math.PI * 2 / 4) * i;
      n.vx = Math.cos(angle) * 1.5;
      n.vy = Math.sin(angle) * 1.5;
      nodes.push(n);
      if (nodes.length > 100) nodes.shift();
    }
  });

  resize(); initNodes(); draw();
  window.addEventListener('resize', function() {
    cancelAnimationFrame(animId);
    resize(); initNodes(); draw();
  });
})();
