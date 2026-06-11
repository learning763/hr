<?php function engTouni($nos)
{
  return str_replace(array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), array('०', '१', '२', '३', '४', '५', '६', '७', '८', '९'), $nos);

}

function uniToeng($nos)
{
  return str_replace(array('०', '१', '२', '३', '४', '५', '६', '७', '८', '९'), array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), $nos);
}
?>