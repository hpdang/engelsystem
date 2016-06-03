<?php

function messages_title() {
  return _("Messages");
}

function user_unread_messages() {
  global $user;
  
  if (isset($user)) {
    $new_messages = sql_num_query("SELECT * FROM `Messages` WHERE isRead='N' AND `RUID`='" . sql_escape($user['UID']) . "'");
    if ($new_messages > 0)
      return ' <span class="badge danger">' . $new_messages . '</span>';
  }
  return '';
}

function user_messages() {
  global $user;
  if (! isset($_REQUEST['action'])) {
   
    $users = sql_select("SELECT * FROM `User` WHERE NOT `UID`='" . sql_escape($user['UID']) . "' ORDER BY `Nick`");
    $groups = sql_select("SELECT * FROM `Groups` ORDER BY `Name`");
    $types = sql_select("SELECT * FROM `AngelTypes` ORDER BY `name`");
    $to_select_data = array(
        "" => _("Select recipient...") 
    );
    
    foreach ($users as $u)
    {
      $to_select_data[$u['UID']] = $u['Nick'];
    }
    foreach ($groups as $gr) {
      $to_select_data[$gr['UID']] = $gr['Name'];
    }
    foreach ($types as $type ) {
      $to_select_data[$type['id']]=$type['name'];
    }
    $to_select_data[0]="All Users";
    $to_select = html_select_key('to', 'to', $to_select_data, '');
    
    $messages = sql_select("SELECT * FROM `Messages` WHERE `SUID`='" . sql_escape($user['UID']) . "' OR `RUID`='" . sql_escape($user['UID']) . "' ORDER BY `isRead`,`Datum` DESC");
    
    $messages_table = [
        [
            'news' => '',
            'timestamp' => date("Y-m-d H:i"),
            'from' => User_Nick_render($user),
            'to' => $to_select,
            'text' => form_textarea('text', '', ''),
            'actions' => form_submit('submit', _("Save")) 
        ] 
    ];
    
    foreach ($messages as $message) {
      $sender_user_source = User($message['SUID']);
      if ($sender_user_source === false)
        engelsystem_error(_("Unable to load user."));
      $receiver_user_source = User($message['RUID']);
      if ($receiver_user_source === false)
        engelsystem_error(_("Unable to load user."));
      
      $messages_table_entry = array(
          'new' => $message['isRead'] == 'N' ? '<span class="glyphicon glyphicon-envelope"></span>' : '',
          'timestamp' => date("Y-m-d H:i", $message['Datum']),
          'from' => User_Nick_render($sender_user_source),
          'to' => User_Nick_render($receiver_user_source),
          'text' => str_replace("\n", '<br />', $message['Text']) 
      );
      
      if ($message['RUID'] == $user['UID']) {
        if ($message['isRead'] == 'N')
          $messages_table_entry['actions'] = button(page_link_to("user_messages") . '&action=read&id=' . $message['id'], _("mark as read"), 'btn-xs');
      } else
        $messages_table_entry['actions'] = button(page_link_to("user_messages") . '&action=delete&id=' . $message['id'], _("delete message"), 'btn-xs');
      $messages_table[] = $messages_table_entry;
    }
    
    return page_with_title(messages_title(), array(
        msg(),
        sprintf(_("Hello %s, here can you leave messages for other angels"), User_Nick_render($user)),
        form(array(
            table(array(
                'new' => _("New"),
                'timestamp' => _("Date"),
                'from' => _("Transmitted"),
                'to' => _("Recipient"),
                'text' => _("Message"),
                'actions' => '' 
            ), $messages_table) 
        ), page_link_to('user_messages') . '&action=send') 
    ));
  } else {
    switch ($_REQUEST['action']) {
      case "read":
        if (isset($_REQUEST['id']) && preg_match("/^[0-9]{1,11}$/", $_REQUEST['id']))
          $id = $_REQUEST['id'];
        else
          return error(_("Incomplete call, missing Message ID."), true);
        
        $message = sql_select("SELECT * FROM `Messages` WHERE `id`='" . sql_escape($id) . "' LIMIT 1");
        if (count($message) > 0 && $message[0]['RUID'] == $user['UID']) {
          sql_query("UPDATE `Messages` SET `isRead`='Y' WHERE `id`='" . sql_escape($id) . "' LIMIT 1");
          redirect(page_link_to("user_messages"));
        } else
          return error(_("No Message found."), true);
        break;
      
      case "delete":
        if (isset($_REQUEST['id']) && preg_match("/^[0-9]{1,11}$/", $_REQUEST['id']))
          $id = $_REQUEST['id'];
        else
          return error(_("Incomplete call, missing Message ID."), true);
        
        $message = sql_select("SELECT * FROM `Messages` WHERE `id`='" . sql_escape($id) . "' LIMIT 1");
        if (count($message) > 0 && $message[0]['SUID'] == $user['UID']) {
          sql_query("DELETE FROM `Messages` WHERE `id`='" . sql_escape($id) . "' LIMIT 1");
          redirect(page_link_to("user_messages"));
        } else
          return error(_("No Message found."), true);
        break;
      
      case "send":
        if (Message_send($_REQUEST['to'], $_REQUEST['text']) === true) {
          redirect(page_link_to("user_messages"));
        } else {
          return error(_("Transmitting was terminated with an Error."), true);
        }
        break;
      
      default:
        return error(_("Wrong action."), true);
    }
  }
}
?>
