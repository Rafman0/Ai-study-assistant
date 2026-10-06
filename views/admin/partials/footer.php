    </div><!-- .admin-content -->
</div><!-- .admin-main -->
</div><!-- .admin-layout -->
<script src="<?php echo asset_url('vendor/bootstrap.bundle.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var b=document.getElementById('admin-sidebar-toggle');
    var ov=document.querySelector('.admin-sidebar-overlay');
    if(b){b.addEventListener('click',function(){
        var open=document.querySelector('.admin-sidebar').classList.toggle('open');
        if(ov){ov.classList.toggle('open',open);}
    });}
    if(ov){ov.addEventListener('click',function(){
        document.querySelector('.admin-sidebar').classList.remove('open');
        ov.classList.remove('open');
    });}
});
</script>
<?php if (!empty($admin_load_charts)): ?>
<script src="<?php echo asset_url('vendor/chart.umd.min.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
