<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <h1><?= $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if (!empty($errors)) { ?>
    <?php foreach($errors as $error) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php } ?>
    <?php } ?>
    <?php if(!empty($success)): ?>
    <div class="alert alert-success">
      <i class="fa fa-check"></i>
      <?= $deleted; ?> product<?= $deleted == 1 ? ' is' : 'en zijn'; ?> verwijderd.
    </div>
    <div class="alert alert-success">
      <i class="fa fa-check"></i>
      <?= $inserted; ?> product<?= $inserted == 1 ? ' is' : 'en zijn'; ?> toegevoegd.
    </div>
    <div class="alert alert-success">
      <i class="fa fa-check"></i>
      <?= $updated; ?> product<?= $updated == 1 ? ' is' : 'en zijn'; ?> bijgewerkt.
    </div>
    <?php endif; ?>
    <?php if(!empty($warnings)): ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> Waarschuwingen</h3>
      </div>
      <table class="table">
        <thead>
          <tr>
            <th>Regel(s)</th>
            <th>Melding</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($warnings as $message => $rows): ?>
          <tr>
            <td>
              <?php
              $n = 5;
              $r = sizeof($rows)-$n;
              echo implode(", ", array_slice($rows, 0, $n));
              if($r > 0) echo " <b>+ {$r} meer</b>";
              ?>
            </td>
            <td style="vertical-align:top">
              <?= $message; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-upload"></i> Import</h3>
      </div>
      <div class="panel-body">
        <form action="" method="post" enctype="multipart/form-data" class="form-horizontal">
          <div class="row">
            <label class="col-sm-2 control-label" for="input-file">Excel-bestand:</label>
            <div class="col-sm-10">
              <input type="file" name="import" id="input-file" class="form-control">
            </div>
          </div>
          <div class="row">
            <div class="col-sm-10 col-sm-offset-2">
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="delete_imported" value="1"
                         <?php if(!empty($_POST['delete_imported'])) echo 'checked'; ?>>
                  Alle eerder geïmporteerde producten (<?= $imported_count; ?>) verwijderen.
                </label>
              </div>
            </div>
          </div>
          <div class="text-right">
            <a href="view/image/kiboimex/voorbeeld.zip" style="margin-right:15px;">Download voorbeeldbestand (ZIP)</a>
            <button type="submit" class="btn btn-primary">Import starten</button>
          </div>
        </form>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-download"></i> Export</h3>
      </div>
      <div class="panel-body">
        <div class="row">
          <div class="col-sm-8 col-sm-offset-2">
            <p>Met de exportfunctionaliteit exporteert u alle producten die eerder
            met behulp van de importfunctionaliteit zijn ingeladen. De gedownloadede
            Excelsheet kunt up bewerken, en opnieuw uploaden.</p>
          </div>
        </div>
        <form action="" method="post">
          <input type="hidden" name="export" value="1">
          <div class="row">
            <div class="col-sm-12 text-right">
              <button class="btn btn-primary" type="submit">Export starten</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>
<?php // vim: set ft=php sw=2 sts=2 et :
