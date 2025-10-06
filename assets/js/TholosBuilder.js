/*
=============================================================================
Copyright (c) 2015 Offsite Solutions Kft.
=============================================================================
$Id:: tholos_builder.js 2020 2024-11-28 10:22:43Z laszlo.banfalvi          $:

$Description: Tholos Application Builder - Javascript

$History:
2015.08.06 Bánfalvi László Létrehozás 
=============================================================================
*/

var clipboardComponentID = "";
var clipboardMethodComponentID = "";
var clipboardMethodID = "";
var clipboardHelpID = "";
var waitForMoveNodes = null;
var movedNodes = "";
var lastComponentId = "";
var componentHistory = [];

function showLoading(container_) {
  if (container_ !== undefined)
    container_.html('<div class="text-center"><i class="fa fa-refresh fa-spin fa-lg"></i></div>');
  else $('#globalLoading').addClass('fa-spin');
}

function finishedLoading() {
  $('#globalLoading').removeClass('fa-spin');
}

/*function initEditorTabs() {
  $('#editortabs').tab();
  $('#editortabs a').off('shown.bs.tab');
  $('#editortabs a').on('shown.bs.tab', function (e) {
    var tabid = $(e.target).attr("href") // activated tab
  });
  $('#editortabs').sortable();
}*/


function getNavFrame() {
  showLoading($('#nav_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=getNavFrame',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#nav_frame').find('.content').html(data.html);
        loadAppTree('#app_tree', '');
      } else {

      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function loadAppTree(treeid_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {'action': 'loadAppTree'},
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      finishedLoading();
      if (data.success == 'OK') {
        $(treeid_).jstree("destroy");
        $(treeid_).jstree(
          {
            'core': {
              'data': JSON.parse(data.tree),
              'multiple': true,
              'animation': 0,
              'themes': {
                'icons': true,
                'variant': 'small'
              },
              'check_callback': function (operation, node, node_parent, node_position, more) {
                // operation can be 'create_node', 'rename_node', 'delete_node', 'move_node' or 'copy_node'
                // in case of 'rename_node' node_position is filled with the new node name
                return (operation === 'rename_node' ||
                  operation === 'move_node') ? true : false;
              }
            },
            'contextmenu': {
              'select_node': false,
              'items': function (node) {
                return {
                  "Edit": {
                    "label": "Edit",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      // showPropertiesAndEvents(obj.original.id, '', '');
                      $('#app_tree').jstree('deselect_all');
                      $('#app_tree').jstree('select_node', obj.original.id).trigger('select_node.jstree');
                    }
                  },
                  "Create child": {
                    "label": "Create child",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      $('#app_tree').jstree('deselect_all');
                      $('#app_tree').jstree('select_node', obj.original.id).trigger('select_node.jstree');
                      addComponent(obj.original.id);
                    }
                  },
                  "Run Query wizard": {
                    "label": "Run Query wizard",
                    "separator_before": true,
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      showQueryWizard(obj.original.id);
                    }
                  },
                  "Run Stored Procedure wizard": {
                    "label": "Run Stored Procedure wizard",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      showStoredProcedureWizard(obj.original.id);
                    }
                  },
                  "Run Grid wizard": {
                    "label": "Run Grid wizard",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      showGridWizard(obj.original.id);
                    }
                  },
                  "Run Form wizard": {
                    "label": "Run Form wizard",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      showEditFormWizard(obj.original.id, '', '');
                    }
                  },
                  "Copy": {
                    "label": "Copy",
                    "separator_before": true,
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      clipboardComponentID = obj.original.id;
                    }
                  },
                  "Paste": {
                    "label": "Paste",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      pasteComponent(obj.original.id, clipboardComponentID);
                    }
                  },
                  "Clone all": {
                    "label": "Clone all selected",
                    "separator_before": true,
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference);
                      bootbox.confirm("Are you sure to clone " + inst.get_selected().length + " component(s)?",
                        function (result) {
                          if (result === true) {
                            var ids = "";
                            for (var i = 0; i < inst.get_selected().length; i++) {
                              ids += (ids == "" ? "" : "|") + inst.get_selected(true)[i].original.id + "," + inst.get_selected(true)[i].original.version;
                            }
                            cloneComponents(ids);
                          }
                        }
                      );
                    }
                  },
                  "Delete": {
                    "label": "Delete",
                    "separator_before": true,
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      bootbox.confirm("Are you sure to delete " + obj.original.name + " component?",
                        function (result) {
                          if (result === true)
                            deleteComponent(obj.original.id, obj.original.version);
                        }
                      );
                    }
                  },
                  "Delete all": {
                    "label": "Delete all",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference);
                      bootbox.confirm("Are you sure to delete " + inst.get_selected().length + " component(s)?",
                        function (result) {
                          if (result === true) {
                            var ids = "";
                            for (var i = 0; i < inst.get_selected().length; i++) {
                              ids += (ids == "" ? "" : "|") + inst.get_selected(true)[i].original.id + "," + inst.get_selected(true)[i].original.version;
                            }
                            deleteComponents(ids);
                          }
                        }
                      );
                    }
                  },
                  "Move to first": {
                    "label": "Move to first",
                    "separator_before": true,
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      moveFirstComponent(obj.original.id, obj.original.version);
                    }
                  },
                  "Move up": {
                    "label": "Move up",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      moveUpComponent(obj.original.id, obj.original.version);
                    }
                  },
                  "Move down": {
                    "label": "Move down",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      moveDownComponent(obj.original.id, obj.original.version);
                    }
                  },
                  "Move to last": {
                    "label": "Move to last",
                    "action": function (data) {
                      var inst = $.jstree.reference(data.reference),
                        obj = inst.get_node(data.reference);
                      moveLastComponent(obj.original.id, obj.original.version);
                    }
                  }
                };
              }
            },
            'state': {'key': 'app_tree'},
            'types': {
              'default': {
                'icon': 'fa fa-cubes color-grey'
              },
              'TApplication': {
                'icon': 'fa fa-university color-green'
              },
              'TRoute': {
                'icon': 'fa fa-random color-purple'
              },
              'TAction': {
                'icon': 'fa fa-arrow-circle-right color-purple'
              },
              'TQuery': {
                'icon': 'fa fa-database color-green'
              },
              'TStoredProcedure': {
                'icon': 'fa fa-download color-blue'
              },
              'TRoleManager': {
                'icon': 'fa fa-lock color-maroon'
              },
              'TPage': {
                'icon': 'fa fa-desktop color-purple2'
              },
              'TPartial': {
                'icon': 'fa fa-bookmark-o color-purple2'
              },
              'TDBParam': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TDataParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TGridParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TLOVParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TDBField': {
                'icon': 'fa fa-list-alt color-lightgreen'
              },
              'TContainer': {
                'icon': 'fa fa-square-o color-brown'
              },
              'TColumn': {
                'icon': 'fa fa-square-o color-brown'
              },
              'TWidget': {
                'icon': 'fa fa-square-o color-brown'
              },
              'TForm': {
                'icon': 'fa fa-external-link color-green'
              },
              'TQueryFilter': {
                'icon': 'fa fa-filter color-green3'
              },
              'TButton': {
                'icon': 'fa fa-caret-square-o-right color-red'
              },
              'TLink': {
                'icon': 'fa fa-caret-square-o-right color-red'
              },
              'TDateTimePicker': {
                'icon': 'fa fa-calendar color-control'
              },
              'TLOV': {
                'icon': 'fa fa-sort-amount-asc color-lightgreen'
              },
              'TGrid': {
                'icon': 'fa fa-table color-grid'
              },
              'TGridColumn': {
                'icon': 'fa fa-columns color-gridcolumn'
              },
              'TGridFilter': {
                'icon': 'fa fa-filter color-green3'
              },
              'TGridRowActions': {
                'icon': 'fa fa-square-o color-red'
              },
              'TTabs': {
                'icon': 'fa fa-folder-o color-brown'
              },
              'TTabPane': {
                'icon': 'fa fa-columns color-brown'
              },
              'TTemplate': {
                'icon': 'fa fa-file-code-o color-green'
              },
              'TImage': {
                'icon': 'fa fa-picture-o color-control'
              },
              'THidden': {
                'icon': 'fa fa-angle-double-right color-grey'
              },
              'TText': {
                'icon': 'fa fa-font color-control'
              },
              'TRadio': {
                'icon': 'fa fa-dot-circle-o color-control'
              },
              'TLabel': {
                'icon': 'fa fa-tag color-control'
              },
              'TEdit': {
                'icon': 'fa fa-pencil-square-o color-control'
              },
              'THTMLEdit': {
                'icon': 'fa fa-header color-control'
              },
              'TCheckbox': {
                'icon': 'fa fa-toggle-on color-control'
              },
              'TStatic': {
                'icon': 'fa fa-tags color-control'
              },
              'TWorkflow': {
                'icon': 'fa fa-forward color-red'
              },
              'TWorkflowStep': {
                'icon': 'fa fa-step-forward color-red'
              },
              'TModal': {
                'icon': 'fa fa-square color-brown'
              },
              'TMap': {
                'icon': 'fa fa-globe color-control'
              },
              'TMapSource': {
                'icon': 'fa fa-map-marker color-control'
              },
              'TWizard': {
                'icon': 'fa fa-magic color-brown'
              },
              'TWizardStep': {
                'icon': 'fa fa-toggle-right color-brown'
              },
              'TFormContainer': {
                'icon': 'fa fa-square-o color-green'
              },
              'TFileUpload': {
                'icon': 'fa fa-upload color-purple'
              },
              'TFileProcessor': {
                'icon': 'fa fa-file color-purple'
              },
              'TJSLib': {
                'icon': 'fa fa-puzzle-piece color-purple'
              },
              'TTimer': {
                'icon': 'fa fa-clock-o color-red'
              },
              'TConfirmDialog': {
                'icon': 'fa fa-question-circle color-red'
              },
              'TConfirmButton': {
                'icon': 'fa fa-caret-square-o-right color-red'
              },
              'TButtonDropdown': {
                'icon': 'fa fa-caret-square-o-down color-red'
              },
              'TDPOpen': {
                'icon': 'fa fa-plug color-green'
              },
              'TCell': {
                'icon': 'fa fa-square-o color-brown'
              },
              'TPDFPage': {
                'icon': 'fa fa-file-pdf-o color-purple2'
              },
              'TExternalDataProvider': {
                'icon': 'fa fa-external-link-square fa-rotate-180 color-green'
              },
              'TJSONDataProvider': {
                'icon': 'fa fa-file-text-o color-green'
              },
              'TDocumentTitle': {
                'icon': 'fa fa-header color-purple2'
              },
              'TIterator': {
                'icon': 'fa fa-sort-amount-asc color-green'
              },
              'TAPIParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TDataProxyParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TGlobalParameter': {
                'icon': 'fa fa-chain color-lightblue'
              },
              'TQueryFilterGroup': {
                'icon': 'fa fa-filter fa-border color-green3'
              },
              'TAPIPost': {
                'icon': 'fa fa-external-link-square color-blue'
              },
              'TButtonDropdownItem': {
                'icon': 'fa fa-caret-square-o-right color-red'
              },
              'TMenuItem': {
                'icon': 'fa fa-caret-square-o-down color-blue'
              },
            },
            'plugins': ['state', 'contextmenu', 'types', 'dnd']
          })
          .bind('select_node.jstree', function (event) {
            if ($(treeid_).jstree().get_selected().length == 1) {
              var obj = $(treeid_).jstree().get_selected(true)[0];
              showPropertiesAndEvents(obj.original.id, '', '');
            }
          })
          .bind('move_node.jstree', function (event, data) {
            // console.log(arguments);
            // console.log(data);
            // console.log(data.node.id,data.node.original.version,data.old_parent,data.parent,data.position);
            if (waitForMoveNodes) {
              clearTimeout(waitForMoveNodes);
            }
            movedNodes = movedNodes + (movedNodes == "" ? "" : "|") + data.node.id + ',' + data.node.original.version + ',' + data.old_parent + ',' + data.parent + ',' + data.position;
            waitForMoveNodes = setTimeout(function () {
              moveMultiple();
            }, 50);
          })
        ;
      } else {
        bootbox.alert(data.errormsg);
      }
      $('#component_tabs').find('a[href="#tab_' + treeid_.replace('#', '') + '"]').tab('show');
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

/* search */

function searchApp(searchFor_) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=searchApp&searchfor=' + searchFor_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame>.content').html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

/* components */

function addComponent(parent_id_) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=addComponent&p_parent_id=' + parent_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame>.content').html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function saveComponent(parent_id_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#form_addComponent').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame>.content').html('');
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function pasteComponent(parent_id_, component_id_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=pasteComponent&p_id=' + component_id_ + '&p_parent_id=' + parent_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

/* moving components */

function moveMultiple() {
  console.log(movedNodes);
  var movedNodes_ = movedNodes;
  movedNodes = "";
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'moveMultiple',
      'p_nodes': movedNodes_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
        loadAppTree('#app_tree');
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function moveFirstComponent(component_id_, version_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=moveFirstComponent&p_id=' + component_id_ + '&p_version=' + version_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function moveUpComponent(component_id_, version_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=moveUpComponent&p_id=' + component_id_ + '&p_version=' + version_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function moveDownComponent(component_id_, version_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=moveDownComponent&p_id=' + component_id_ + '&p_version=' + version_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function moveLastComponent(component_id_, version_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=moveLastComponent&p_id=' + component_id_ + '&p_version=' + version_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

// function changeParentComponent(component_id_,parent_id_) {
//     showLoading();
//     $.ajax({
//         url: __editorURL,
//         type: 'post',
//         dataType: 'json',
//         data: 'action=changeParentComponent&p_id='+component_id_+'&p_parent_id='+parent_id_,
//         contentType: "application/x-www-form-urlencoded;charset=UTF-8",
//         success: function (data) {
//             if (data.success=='OK') {
//                 loadAppTree('#app_tree',appTreeRouteFilter);
//             } else {
//                 bootbox.alert(data.errormsg);
//             }
//             finishedLoading();
//         },
//         error: function(response, textStatus, errorThrown) {
//
//         }
//     });
// }

function deleteComponent(component_id_, version_) {
  showLoading();
  showPropertiesAndEvents(-1, '', '');
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=deleteComponent&p_id=' + component_id_ + '&p_version=' + version_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function deleteComponents(component_ids_) {
  showLoading();
  showPropertiesAndEvents(-1, '', '');
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=deleteComponents&p_ids=' + component_ids_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function cloneComponents(component_ids_) {
  showLoading();
  showPropertiesAndEvents(-1, '', '');
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=cloneComponents&p_ids=' + component_ids_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
      }
      finishedLoading();
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function addToHistory(component_id_) {
  var inHistory = false;
  for (var i = 0; i <= componentHistory.length - 1; i++) {
    if (componentHistory[i] == component_id_) {
      inHistory = true;
      break;
    }
  }
  if (!inHistory) componentHistory[componentHistory.length] = component_id_;
}

function historyStepBack() {
  var backIndex = -1;
  for (var i = 0; i <= componentHistory.length - 1; i++) {
    if (componentHistory[i] == lastComponentId) {
      backIndex = i - 1;
      break;
    }
  }
  if (backIndex > -1) {
    $('#app_tree').jstree('deselect_all');
    $('#app_tree').jstree('select_node', '#' + componentHistory[backIndex]);
  }
}

function historyStepForward() {
  var backIndex = -1;
  for (var i = 0; i <= componentHistory.length - 1; i++) {
    if (componentHistory[i] == lastComponentId) {
      backIndex = i + 1;
      break;
    }
  }
  if (backIndex < componentHistory.length) {
    $('#app_tree').jstree('deselect_all');
    $('#app_tree').jstree('select_node', '#' + componentHistory[backIndex]);
  }
}

/* properties */

function showPropertiesAndEvents(component_id_, property_id_, event_id_) {
  if ($('#prop_frame #prop_tabs').length == 0) {
    $.ajax({
      url: __editorURL,
      type: 'post',
      dataType: 'json',
      data: {'action': 'showPropertiesAndEventsHead'},
      contentType: "application/x-www-form-urlencoded;charset=UTF-8",
      success: function (data) {
        if (data.success == 'OK') {
          $('#prop_frame').find('.content').html(data.html);
          $('#prop_frame .content').tabs({active: 0});
          $('#prop_frame .content').tabs({
            activate: function (event, ui) {
              showPropertiesAndEvents('', '', '');
            }
          });
          showPropertiesAndEvents(component_id_, property_id_, event_id_);
        } else {
          bootbox.alert(data.errormsg);
        }
        finishedLoading();
      },
      error: function (response, textStatus, errorThrown) {

      }
    });
  } else {
    var tabIndex = $('#prop_frame .content').tabs('option', 'active');
    if (component_id_ == '') component_id_ = lastComponentId;
    else lastComponentId = component_id_;
    if (property_id_ == '' && event_id_ == '')
      showLoading($('#prop_frame').find('#tab_prop_' + tabIndex));
    else showLoading();
    $.ajax({
      url: __editorURL,
      type: 'post',
      dataType: 'json',
      data: {
        'action': 'showPropertiesAndEvents',
        'p_component_id': component_id_,
        'p_property_id': property_id_,
        'p_event_id': event_id_,
        'p_tab_index': tabIndex
      },
      contentType: "application/x-www-form-urlencoded;charset=UTF-8",
      success: function (data) {
        if (data.success == 'OK') {
          if (property_id_ == '' && event_id_ == '') $('#prop_frame').find('#tab_prop_' + tabIndex).html(data.html);
          else if (property_id_ != '') $('#prop_frame #prop_row_' + property_id_).replaceWith(data.html);
          else $('#prop_frame #event_row_' + event_id_).replaceWith(data.html);
        } else {
          bootbox.alert(data.errormsg);
        }
        finishedLoading();
        addToHistory(component_id_);
      },
      error: function (response, textStatus, errorThrown) {

      }
    });
  }
}

function editProperty(component_id_, property_id_, type_, link_id_, version_) {
  showLoading($('#prop_' + property_id_));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'editProperty',
      'p_component_id': component_id_,
      'p_property_id': property_id_,
      'p_type': type_,
      'p_link_id': link_id_,
      'p_version': version_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#prop_' + property_id_).html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function saveProperty(component_id_, property_id_, link_id_, version_, value_, value_component_id_) {
  $('#prop_frame #prop_row_' + property_id_ + ' .saveIcon').addClass('fa-spin');
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'saveProperty',
      'p_component_id': component_id_,
      'p_property_id': property_id_,
      'p_id': link_id_,
      'p_version': version_,
      'p_value': value_,
      'p_value_component_id': value_component_id_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        if (data.refreshtree == 'Y') loadAppTree('#app_tree');
        else showPropertiesAndEvents(component_id_, property_id_, '');
      } else {
        $('#prop_frame #prop_row_' + property_id_ + ' .saveIcon').removeClass('fa-spin');
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

/* events */

function editEvent(component_id_, event_id_, link_id_, version_) {
  showLoading($('#event_' + event_id_));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'editEvent',
      'p_component_id': component_id_,
      'p_event_id': event_id_,
      'p_link_id': link_id_,
      'p_version': version_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#event_' + event_id_).html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function loadEventComponents(event_id_, component_id_, search_) {
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'loadEventComponents',
      'p_component_id': component_id_,
      'p_event_id': event_id_,
      'p_search': search_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function loadMethods(event_id_, component_id_) {
  showLoading($('#event_methods_' + event_id_));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'loadMethods',
      'p_component_id': component_id_,
      'p_event_id': event_id_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#event_methods_' + event_id_).html(data.html);
      } else {
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function saveEvent(component_id_, event_id_, link_id_, version_, value_, value_component_id_, value_method_id_, parameters_) {
  $('#prop_frame #event_row_' + event_id_ + ' .saveIcon').addClass('fa-spin');
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {
      'action': 'saveEvent',
      'p_component_id': component_id_,
      'p_event_id': event_id_,
      'p_id': link_id_,
      'p_version': version_,
      'p_value': value_,
      'p_value_component_id': value_component_id_,
      'p_value_method_id': value_method_id_,
      'p_parameters': parameters_
    },
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        if (data.refreshtree == 'Y') loadAppTree('#app_tree');
        else showPropertiesAndEvents(component_id_, '', event_id_);
      } else {
        $('#prop_frame #event_row_' + event_id_ + ' .saveIcon').removeClass('fa-spin');
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function copyMethod(component_id, method_id) {
  clipboardMethodComponentID = component_id;
  clipboardMethodID = method_id;
}

/* other actions */

function showComponentTypeDocumentation() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: {'action': 'showComponentTypeDocumentation'},
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      $('#edit_frame').find('.content').html(data.html);
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function compile(all) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=compile2&compileall=' + (all),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        toastr["success"]("Compiled");
      } else {
        finishedLoading();
        bootbox.alert(data.errormsg);
      }
    },
    error: function (response, textStatus, errorThrown) {

    },
    timeout: 1800000
  });
}

/* wizards */

function showQueryWizard(component_id_ = '') {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showQueryWizard&p_id=' + component_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showTranslate() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showTranslate',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function QueryWizardRun(p_component_id_, p_trans_root_, p_skip_label_, todo_) {
  showLoading($('#wizard_result'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=QueryWizardRun&p_component_id=' + p_component_id_ + '&p_trans_root=' + p_trans_root_ + '&p_skip_label=' + p_skip_label_ + '&todo=' + todo_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#wizard_result').html(data.html);
        if (todo_ == 'save') loadAppTree('#app_tree');
      } else {
        if (data.html && data.html.length > 0) $('#wizard_result').html(data.html);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showStoredProcedureWizard(component_id_ = '') {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showStoredProcedureWizard&p_id=' + component_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function StoredProcedureWizardRun(p_component_id_, todo_) {
  showLoading($('#wizard_result'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=StoredProcedureWizardRun&p_component_id=' + p_component_id_ + '&todo=' + todo_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#wizard_result').html(data.html);
        if (todo_ == 'save') loadAppTree('#app_tree');
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showGridWizard(p_grid_id_) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showGridWizard&p_grid_id=' + p_grid_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function GridWizardRun() {
  showLoading($('#edit_frame').find('.content').find('#gridFormPost'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#gridForm').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        loadAppTree('#app_tree');
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showEditFormWizard(p_form_id_, p_query_id_, p_blacklist_) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showEditFormWizard&p_form_id=' + p_form_id_ + '&p_query_id=' + p_query_id_ + '&p_editformwizardblacklist=' + p_blacklist_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function EditFormWizardRun() {
  showLoading($('#edit_frame').find('.content').find('#editformFormPost'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#editformForm').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        loadAppTree('#app_tree');
      } else {
        bootbox.alert("Error processing request");
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function EditHelp(p_help_id_, p_component_id_) {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showEditHelp&p_help_id=' + p_help_id_ + '&p_component_id=' + p_component_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function refreshHelp(p_component_id_) {
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showHelpInfo&p_component_id=' + p_component_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#tab_prop_4').html(data.html);
      } else {

      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
    }
  });
}

function saveHelp() {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#helpForm').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#helpForm').find('#p_id').val(data.p_id);
        $('#helpForm').find('#p_version').val(data.p_version);
        toastr["success"]("Saved");
        finishedLoading();
        refreshHelp($('#helpForm').find('#p_component_id').val());
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function deleteHelp(p_id_, p_version_, p_component_id_) {
  if (p_id_ != '') {
    bootbox.confirm("Are you sure?",
      function (result) {
        if (result === true) {
          $.ajax({
            url: __editorURL,
            type: 'post',
            dataType: 'json',
            data: 'action=deleteHelp&p_id=' + p_id_ + '&p_version=' + p_version_,
            contentType: "application/x-www-form-urlencoded;charset=UTF-8",
            success: function (data) {
              if (data.success == 'OK') {
                $('#edit_frame').find('.content').html('');
                toastr["success"]("Deleted");
                finishedLoading();
                refreshHelp(p_component_id_);
              } else {
                bootbox.alert(data.errormsg);
                finishedLoading();
              }
            },
            error: function (response, textStatus, errorThrown) {
              bootbox.alert("AJAX call error");
              finishedLoading();
            }
          });
        }
      }
    );
  }
}

function copyHelp(p_help_id_) {
  clipboardHelpID = p_help_id_;
  toastr["success"]("Copied");
}

function pasteHelp(p_component_id_) {
  if (clipboardHelpID != '')
    $.ajax({
      url: __editorURL,
      type: 'post',
      dataType: 'json',
      data: 'action=saveHelp&p_component_id=' + p_component_id_ + '&p_help_id=' + clipboardHelpID,
      contentType: "application/x-www-form-urlencoded;charset=UTF-8",
      success: function (data) {
        if (data.success == 'OK') {
          toastr["success"]("Saved");
          finishedLoading();
          refreshHelp(p_component_id_);
        } else {
          bootbox.alert(data.errormsg);
          finishedLoading();
        }
      },
      error: function (response, textStatus, errorThrown) {
        bootbox.alert("AJAX call error");
        finishedLoading();
      }
    });
}

function generateUserHelp() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=generateUserGuide',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function chooseTask(form) {
  var data = (form ? form.serialize() : 'action=chooseTask');
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: data,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function openTask(p_task_number_, p_subject_, p_project_id_) {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=openTask&p_task_number=' + p_task_number_ + '&p_subject=' + p_subject_ + '&p_project_id=' + p_project_id_,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        reloadTaskFrame();
        $('#edit_frame').find('.content').html('');
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function closeTask() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=closeTask',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        reloadTaskFrame();
        $('#edit_frame').find('.content').html('');
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showCommitHistory() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showCommitHistory',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showUserProfile() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showUserProfile',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function saveUserProfile() {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#userprofile').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        toastr["success"]("Saved");
        finishedLoading();
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function showOpenedTasks() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showOpenedTasks',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function commitChanges() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=commitChanges',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function saveCommitChanges() {
  var data = $('#issues');
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: data.serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        finishedLoading();
      } else {
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function reloadTaskFrame() {
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=reloadTaskFrame',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#task_frame').html(data.html);
      } else {
      }
    },
    error: function (response, textStatus, errorThrown) {

    }
  });
}

function showRoutes() {
  showLoading($('#edit_frame').find('.content'));
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=showRoutes',
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function addRoute($route_id) {
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: 'action=addRoute&route_id=' + $route_id,
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        loadAppTree('#app_tree');
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

function setFilterRoute() {
  showLoading();
  $.ajax({
    url: __editorURL,
    type: 'post',
    dataType: 'json',
    data: $('#setFilterRoute').serialize(),
    contentType: "application/x-www-form-urlencoded;charset=UTF-8",
    success: function (data) {
      if (data.success == 'OK') {
        $('#edit_frame').find('.content').html(data.html);
        loadAppTree('#app_tree');
        componentHistory = [];
      } else {
        bootbox.alert(data.errormsg);
        finishedLoading();
      }
    },
    error: function (response, textStatus, errorThrown) {
      bootbox.alert("AJAX call error");
      finishedLoading();
    }
  });
}

$(document).ready(function () {

  $(document).ajaxComplete(function (event, request, settings) {
    if (request.getResponseHeader('X-Tholos-Redirect') && request.getResponseHeader('X-Tholos-Redirect').length > 0) {
      window.location = request.getResponseHeader('X-Tholos-Redirect');
    }
  });

  var minWidth = 200;

  $("#nav_frame").resizable({
    autoHide: false,
    handles: 'e',
    minWidth: 200,
    resize: function (e, ui) {
      var parentWidth = ui.element.parent().width();
      var remainingSpace = parentWidth - $('#prop_frame').outerWidth() - ui.element.outerWidth();

      if (remainingSpace < minWidth) {
        ui.element.width((parentWidth - minWidth) / parentWidth * 100 + "%");
        remainingSpace = minWidth;
      }
      var divTwo = $('#edit_frame'),
        divTwoWidth = (remainingSpace - (divTwo.outerWidth() - divTwo.width())) / parentWidth * 100 + "%";
      divTwo.width(divTwoWidth);
    },
    stop: function (e, ui) {
      var parentWidth = ui.element.parent().width();
      ui.element.css({
        width: ui.element.width() / parentWidth * 100 + "%"
      });
    }
  });

  $("#prop_frame").resizable({
    autoHide: false,
    handles: 'e',
    minWidth: 200,
    resize: function (e, ui) {
      var parentWidth = ui.element.parent().width();
      var remainingSpace = parentWidth - $('#nav_frame').outerWidth() - ui.element.outerWidth();

      if (remainingSpace < minWidth) {
        ui.element.width((parentWidth - minWidth) / parentWidth * 100 + "%");
        remainingSpace = minWidth;
      }
      var divTwo = ui.element.next(),
        divTwoWidth = (remainingSpace - (divTwo.outerWidth() - divTwo.width())) / parentWidth * 100 + "%";
      divTwo.width(divTwoWidth);
    },
    stop: function (e, ui) {
      var parentWidth = ui.element.parent().width();
      ui.element.css({
        width: ui.element.width() / parentWidth * 100 + "%"
      });
    }
  });

  if (skipframeloading !== true) {
    getNavFrame();
    reloadTaskFrame();
    showRoutes();
  }

  addHandler(document, "keydown", disabler);

});