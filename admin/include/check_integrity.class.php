<?php
// +-----------------------------------------------------------------------+
// | PhpWebGallery - a PHP based picture gallery                           |
// | Copyright (C) 2002-2003 Pierrick LE GALL - pierrick@phpwebgallery.net |
// | Copyright (C) 2003-2008 PhpWebGallery Team - http://phpwebgallery.net |
// +-----------------------------------------------------------------------+
// | file          : $Id$
// | last update   : $Date$
// | last modifier : $Author$
// | revision      : $Revision$
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

class check_integrity
{
  var $ignore_list;
  var $retrieve_list;
  var $build_ignore_list;

  function check_integrity()
  {
    $this->ignore_list = array();
    $this->retrieve_list = array();
    $this->build_ignore_list = array();
  }

  /**
   * Check integrities
   *
   * @param void
   * @return void
   */
  function check()
  {
    global $page, $header_notes, $conf;

    // Ignore list
    $conf_c13y_ignore = unserialize($conf['c13y_ignore']);
    if (
          is_array($conf_c13y_ignore) and
          isset($conf_c13y_ignore['version']) and
          ($conf_c13y_ignore['version'] == PHPWG_VERSION) and
          is_array($conf_c13y_ignore['list'])
        )
    {
      $ignore_list_changed = false;
      $this->ignore_list = $conf_c13y_ignore['list'];
    }
    else
    {
      $ignore_list_changed = true;
      $this->ignore_list = array();
    }

    // Retrieve list
    $this->retrieve_list = array();
    $this->build_ignore_list = array();

    trigger_action('list_check_integrity', $this);

    // Information
    if (count($this->retrieve_list) > 0)
    {
      $header_notes[] =
        l10n_dec('c13y_anomaly_count', 'c13y_anomalies_count',
          count($this->retrieve_list));
    }

    // Treatments
    if (!is_adviser())
    {
      if (isset($_POST['c13y_submit_correction']) and isset($_POST['c13y_selection']))
      {
        $corrected_count = 0;
        $not_corrected_count = 0;

        foreach ($this->retrieve_list as $i => $c13y)
        {
          if (!empty($c13y['correction_fct']) and
              $c13y['is_callable'] and
              in_array($c13y['id'], $_POST['c13y_selection']))
          {
            if (is_array($c13y['correction_fct_args']))
            {
              $args = $c13y['correction_fct_args'];
            }
            else
            if (!is_null($c13y['correction_fct_args']))
            {
              $args = array($c13y['correction_fct_args']);
            }
            else
            {
              $args = array();
            }
            $this->retrieve_list[$i]['corrected'] = call_user_func_array($c13y['correction_fct'], $args);

            if ($this->retrieve_list[$i]['corrected'])
            {
              $corrected_count += 1;
            }
            else
            {
              $not_corrected_count += 1;
            }
          }
        }

        if ($corrected_count > 0)
        {
          $page['infos'][] =
            l10n_dec('c13y_anomaly_corrected_count', 'c13y_anomalies_corrected_count',
              $corrected_count);
        }
        if ($not_corrected_count > 0)
        {
          $page['errors'][] =
            l10n_dec('c13y_anomaly_not_corrected_count', 'c13y_anomalies_not_corrected_count',
              $not_corrected_count);
        }
      }
      else
      {
        if (isset($_POST['c13y_submit_ignore']) and isset($_POST['c13y_selection']))
        {
          $ignored_count = 0;

          foreach ($this->retrieve_list as $i => $c13y)
          {
            if (in_array($c13y['id'], $_POST['c13y_selection']))
            {
              $this->build_ignore_list[] = $c13y['id'];
              $this->retrieve_list[$i]['ignored'] = true;
              $ignored_count += 1;
            }
          }

          if ($ignored_count > 0)
          {
            $page['infos'][] =
              l10n_dec('c13y_anomaly_ignored_count', 'c13y_anomalies_ignored_count',
                $ignored_count);
          }
        }
      }
    }

    $ignore_list_changed =
      (
        ($ignore_list_changed) or
        (count(array_diff($this->ignore_list, $this->build_ignore_list)) > 0) or
        (count(array_diff($this->build_ignore_list, $this->ignore_list)) > 0)
        );

    if ($ignore_list_changed)
    {
      $this->update_conf($this->build_ignore_list);
    }
  }

  /**
   * Display anomalies list
   *
   * @param void
   * @return void
   */
  function display()
  {
    global $template;

    $check_automatic_correction = false;
    $submit_automatic_correction = false;
    $submit_ignore = false;

    if (isset($this->retrieve_list) and count($this->retrieve_list) > 0)
    {
      $template->set_filenames(array('check_integrity' => 'admin/check_integrity.tpl'));

      foreach ($this->retrieve_list as $i => $c13y)
      {
        $can_select = false;

        $template->assign_block_vars('c13y',
          array(
           'CLASS' => ($i % 2 == 1) ? 'row2' : 'row1',
           'ID' => $c13y['id'],
           'ANOMALY' => $c13y['anomaly']
          ));


        if (isset($c13y['ignored']))
        {
          if ($c13y['ignored'])
          {
            $template->assign_block_vars('c13y.ignore_msg', array());
          }
          else
          {
            die('$c13y[\'ignored\'] cannot be false');
          }
        }
        else
        {
          if (!empty($c13y['correction_fct']))
          {
            if (isset($c13y['corrected']))
            {
              if ($c13y['corrected'])
              {
                $template->assign_block_vars('c13y.correction_success_fct', array());
              }
              else
              {
                $template->assign_block_vars('c13y.correction_error_fct',
                  array('WIKI_FOROM_LINKS' => $this->get_htlm_links_more_info()));
              }
            }
            else if ($c13y['is_callable'])
            {
              $template->assign_block_vars('c13y.correction_fct', array());
              $template->assign_block_vars('c13y_link_check_automatic_correction.c13y_do_check', array('ID' => $c13y['id']));
              $submit_automatic_correction = true;
              $can_select = true;
            }
            else
            {
              $template->assign_block_vars('c13y.correction_bad_fct', array());
              $can_select = true;
            }
          }
          else
          {
            $can_select = true;
          }

          if (!empty($c13y['correction_fct']) and !empty($c13y['correction_msg']))
          {
            $template->assign_block_vars('c13y.br', array());
          }

          if (!empty($c13y['correction_msg']) and !isset($c13y['corrected']))
          {
            $template->assign_block_vars('c13y.correction_msg',
              array(
               'DATA' => nl2br($c13y['correction_msg'])
              ));
          }
        }

        if ($can_select)
        {
          $template->assign_block_vars('c13y.can_select', array());
          $submit_ignore = true;
        }
      }

      if ($submit_automatic_correction)
      {
        $template->assign_block_vars('c13y_submit_automatic_correction', array());
      }

      if ($submit_ignore)
      {
        $template->assign_block_vars('c13y_link_check_uncheck', array());
        $template->assign_block_vars('c13y_submit_ignore', array());
      }

      $template->concat_var('ADMIN_CONTENT', $template->parse('check_integrity', true) );
    }
  }

  /**
   * Add anomaly data
   *
   * @param anomaly arguments
   * @return void
   */
  function add_anomaly($anomaly, $correction_fct = null, $correction_fct_args = null, $correction_msg = null)
  {
    $id = md5($anomaly.$correction_fct.serialize($correction_fct_args).$correction_msg);

    if (in_array($id, $this->ignore_list))
    {
      $this->build_ignore_list[] = $id;
    }
    else
    {
      $this->retrieve_list[] =
        array(
          'id' => $id,
          'anomaly' => $anomaly,
          'correction_fct' => $correction_fct,
          'correction_fct_args' => $correction_fct_args,
          'correction_msg' => $correction_msg,
          'is_callable' => is_callable($correction_fct));
    }
  }

  /**
   * Update table config
   *
   * @param ignore list array
   * @return void
   */
  function update_conf($conf_ignore_list = array())
  {
    $conf_c13y_ignore =  array();
    $conf_c13y_ignore['version'] = PHPWG_VERSION;
    $conf_c13y_ignore['list'] = $conf_ignore_list;
    $query = 'update '.CONFIG_TABLE.' set value =\''.serialize($conf_c13y_ignore).'\'where param = \'c13y_ignore\';';
    pwg_query($query);
  }

  /**
   * Apply maintenance
   *
   * @param void
   * @return void
   */
  function maintenance()
  {
    $this->update_conf();
  }

  /**
   * Returns links more informations
   *
   * @param void
   * @return html links
   */
  function get_htlm_links_more_info()
  {
    $pwg_links = pwg_URL();
    $link_fmt = '<a href="%s" onclick="window.open(this.href, \'\'); return false;">%s</a>';
    return
      sprintf
      (
        l10n('c13y_more_info'),
        sprintf($link_fmt, $pwg_links['FORUM'], l10n('c13y_more_info_forum')),
        sprintf($link_fmt, $pwg_links['WIKI'], l10n('c13y_more_info_wiki'))
      );
  }

}

?>
