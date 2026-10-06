<?php
/**
 *
 * Knowledge base. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, Sheer
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, [
//Configuration
'KB_NOT_WRITABLE'                            => 'The "files" or "plupload" folders cannot be automatically set to write permissions.<br>Please set the folders permissions to 0777 manually.',
'KB_FONT_ICON'                               => 'Knowledge Base link icon',
'KB_FONT_ICON_EXPLAIN'                       => 'Enter the name of a <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank"><strong>Font Awesome</strong></a> icon to use for the Knowledge Base link in the header.<br> Leave this field blank for no Knowledge Base icon.',
'KB_FONT_ICON_INVALID'                       => 'The Knowledge Base link icon contained invalid characters.',
'KB_CONFIG_EXPLAIN'                          => 'Здесь вы можете задать основные настройки',
'ANOUNCE'                                    => 'Анонсировать новые статьи на форуме',
'ANOUNCE_EXPLAIN'                            => 'Если включено, при добавлении новой статьи в выбранном ниже форуме будет автоматически создана тема с кратким описанием статьи и ссылкой на неё.',
'KB_FORUM_EXPLAIN'                           => 'Выберите форум, в который будут отправляться анонсы статей.',
'KB_NONE_DISABLED'                           => '-- Нет (отключено) --',
'KB_CHANGELOG_FORUM'                         => 'Форум журнала изменений',
'KB_CHANGELOG_FORUM_EXPLAIN'                 => 'Тема журнала изменений для каждой статьи, видимая только модераторам, создаётся здесь при каждой отправке, редактировании и правке статьи — отдельно от форума анонсов выше, предназначенного для обычных участников. Оставьте пустым, чтобы отключить эту функцию.',
'PER_PAGE'                                   => 'Количество статей на странице',
'PER_PAGE_EXPLAIN'                           => 'Количество статей на странице управления статьями и странице просмотра категории.',
'SORT_TYPE'                                  => 'Порядок сортировки',
'SORT_TYPE_EXPLAIN'                          => 'Если выбрано <strong>принудительно</strong>, то пользователь, имеющий модераторское право редактировать статьи, может расположить их внутри категории в произвольном порядке.<br>														Если выбрано <strong>выборочно</strong>, то пользователи могут сортировать статьи внутри категории обычным порядком.<br>														Если выбрано <strong>алфавитная навигация</strong>, то статьи отображаются в алфавитном порядке, при этом имеется возможность фильтровать статьи по первой букве заголовка.',
'KB_SORT_POSITION'                           => 'Позиция',
'FORCIBLY'                                   => 'принудительно',
'SELECTABLE'                                 => 'выборочно',
'ALPHABET'                                   => 'алфавитная навигация',
'EXTENSION_GROUP_EXPLAIN'                    => 'Здесь вы можете управлять разрешёнными расширениями файлов вложений. Если вы желаете удалить расширение, в выпадающем меню слева выберите только те, которые следует оставить. Если вы хотите добавить расширения, выберите их в выпадающем списке доступных расширений слева. Используя соответствующие комбинации клавиш и кнопок мыши, можно выбрать более одного расширения. Если вы хотите добавить расширения, отсутствующие в списке доступных, их следует добавить в соответствующем разделе ACP (<strong>Сообщения</strong> &raquo; ВЛОЖЕНИЯ &raquo; Управление расширениями файлов). Если вы желаете использовать другие группы расширений, их нужно также добавить в ACP.',
'FORUM_PREFIX'                               => 'Forum Prefix',
'FORUM_PREFIX_EXPLAIN'                       => 'The text displayed before the forum name.<br>You can use html entities e.g. &amp;bull; &bull;&nbsp;&nbsp;&nbsp;&amp;laquo; &laquo;&nbsp;&nbsp;&nbsp;&amp;raquo; &raquo;',
'TOPIC_PREFIX'                               => 'Topic Prefix',
'TOPIC_PREFIX_EXPLAIN'                       => 'The text displayed before the topic name.<br>You can use html entities e.g. &amp;bull; &bull;&nbsp;&nbsp;&nbsp;&amp;laquo; &laquo;&nbsp;&nbsp;&nbsp;&amp;raquo; &raquo;',

//Manage category
'ACP_LIBRARY_MANAGE_EXPLAIN'                 => 'Каждая категория может иметь неограниченное количество подкатегорий. Здесь вы можете добавлять, редактировать, перемещать категории местами и перемещать категории из одной в другую. Если количество статей в категории не совпадает с реальным, вы можете синхронизировать категорию.',
'CATEGORY_LIST'                              => 'Список категорий',
'SELECT_CATEGORY'                            => 'Выберите категорию',
'SELECTION_CATEGORY'                         => 'Выберите категорию',
'ADD_CATEGORY'                               => 'Добавить категорию',
'ADD_CATEGORY_EXPLAIN'                       => 'Создать новую категорию',
'CATEGORY_ADDED'                             => 'Категория успешно добавлена. Теперь вы можете %sустановить права%s доступа для этой категории.',
'CATEGORY_DELETED'                           => 'Категория успешно удалена.',
'CATEGORY_EDITED'                            => 'Категория успешно отредактирована',
'CAT_PARENT'                                 => 'Родительская категория',
'CAT_NAME'                                   => 'Название категории',
'CAT_DESCR'                                  => 'Описание категории',
'CAT_DEFAULT_TEAM'                           => 'Команда по умолчанию',
'CAT_DEFAULT_TEAM_EXPLAIN'                   => 'Статья в этой категории без явно назначенной команды попадает в очередь этой команды.',
'CAT_DEFAULT_TEAM_NONE'                      => 'Нет',
'COPY_CAT_PERMISSIONS'                       => 'Копировать права доступа из',
'COPY_CAT_PERMISSIONS_EXPLAIN'               => 'Категории будут присвоены те же права доступа, что и у выбранной из списка.',
'KB_ROOT'                                    => 'Корневая категория',
'NO_CAT_NAME'                                => 'Вы не указали название категории.',
'DELETE_SUBCATS'                             => 'Удалить подкатегории и статьи',
'DEL_CATEGORY'                               => 'Удалить категорию',
'DEL_CATEGORY_EXPLAIN'                       => 'Форма ниже позволяет вам удалить категорию. Вы можете решить, куда переместить все имеющиеся в ней статьи или подкатегории.',
'LIBRARY_EDIT_CAT'                           => 'Редактирование категории',
'LIBRARY_EDIT_CAT_EXPLAIN'                   => 'Здесь вы можете переименовать категорию, дать ей краткое описание и переместить в другую категорию (вместе с содержимым).',
'MOVE_SUBCATS_TO'                            => 'Переместить подкатегории в',
'NO_CATS_IN_KB'                              => 'В библиотеке пока нет категорий.',
'NO_DESTINATION_CATEGORY'                    => 'Категория получатель не найдена',
'NO_PARENT'                                  => 'Нет родителя',

//Article
'ARTICLE_MANAGE_EXPLAIN'                     => 'Здесь вы можете удалять статьи или перемещать их в другие категории, а также просматривать их или редактировать (в отдельном окне).',
'ARTICLE_MOVE_EXPLAIN'                       => 'Выберите категорию, в которую вы желаете перенести статью.',
'EDIT_DATE'                                  => 'Отредактирована',
'MOVE_ARTICLES_TO'                           => 'Переместить статьи в',
'DELETE_ALL_ARTICLES'                        => 'Удалить статьи',
'NO_ARTICLES_IN_KB'                          => 'В библиотеке пока нет статей.',
'NO_ARTICLES_IN_CATEGORY'                    => 'В этой категории пока нет статей.',

//Permission
'ACP_LIBRARY_PERMISSIONS'                    => 'Права доступа',
'ACP_LIBRARY_PERMISSIONS_MASK'               => 'Маски прав доступа',
'ACP_LIBRARY_PERMISSIONS_NO_CATS'            => 'Чтобы задать права доступа, необходимо создать хотя бы одну категорию.',
'ACP_LIBRARY_PERMISSIONS_EXPLAIN'            => 'Здесь вы можете изменять для каждого пользователя и группы доступ к каждой категории библиотеки. Для назначения модераторов или определения прав администратора используйте соответствующую страницу.',
'ALL_CATS'                                   => 'Все категории',
'NO_COPY_PERMISSIONS'                        => 'Не копировать права доступа',
// User Permissions
'kb_u_add'                                   => 'Может добавлять статьи',
'kb_u_edit'                                  => 'Может редактировать свои статьи',
'kb_u_delete'                                => 'Может удалять свои статьи',
'kb_u_add_noapprove'                         => 'Может добавлять статьи без предварительного одобрения',
// Moderator Permissions
'kb_m_edit'                                  => 'Может редактировать статьи',
'kb_m_delete'                                => 'Может удалять статьи',
'kb_m_approve'                               => 'Может одобрять статьи',

//Search
'YES_SEARCH_EXPLAIN'                         => 'Включение поисковых возможностей поиска в статьях.',
'PER_PAGE_SEARCH'                            => 'Результаты поиска',
'PER_PAGE_SEARCH_EXPLAIN'                    => 'Количество отображаемых элементов на странице результатов поиска.',
'SEARCH_TYPE_EXPLAIN'                        => 'Расширение <strong>phpBB Knowledge Base</strong> позволяет выбрать механизм для осуществления поиска в статьях. По умолчанию используется собственный полнотекстовый поисковый механизм Knowledge Base.',

//Attachments
'ATTACHMENTS_EXPLAIN'                        => 'Здесь можно просматривать и удалять вложения, прикреплённые к статьям.',
'ACP_LIBRARY_ATTACHMENTS_LOST_FILES_EXPLAIN' => 'Данный инструмент сравнивает записи в Базе Данных с фактом нахождения вложений на сервере. Если файлы вложений отсутствуют на сервере, инструмент удаляет записи о них из Базы Данных. Продолжить?.',
'PRUNE_ATTACHMENTS_EXPLAIN'                  => 'Будет осуществлена проверка существования на сервере лишних файлов вложений. Если будет обнаружено, что файл существует, он будет удален. Продолжить?',
'ORPHAN_EXPLAIN'                             => 'Здесь вы можете видеть потерянные файлы. Обычно такие файлы появляются из-за того, что пользователи прикрепляют файлы, но впоследствии не публикуют статью. Вы можете удалить такие файлы или прикрепить их к существующим статьям. Во втором случае вам потребуется правильный идентификатор статьи (ID), который вы должны указать самостоятельно. После этого загруженное вложение будет прикреплено к указанной вами статье, если она существует.',
'FILES_DELETED_SUCCESS'                      => 'Выбранные вложения были удалены.',
'NO_FILES_SELECTED'                          => 'Не выбрано вложений для удаления.',
'PRUNE_ATTACHMENTS_FINISHED'                 => 'Лишних файлов вложений не обнаружено.',
'PRUNE_ATTACHMENTS_PROGRESS'                 => 'Проводится проверка лишних файлов. Не прерывайте процесс!<br>Следующие файлы были удалены:',
'PRUNE_ATTACHMENTS_FAIL'                     => '<br>Следующие файлы не удалось удалить:',
'POST_ROW_ARTICLE_INFO'                      => ' под номером %1$d…',
'RESYNC_ATTACHMENTS_FINISHED'                => 'Вложения успешно синхронизированы (проверены на корректность записей в Базе Данных)!',
'RESYNC_ATTACHMENTS_PROGRESS'                => 'Производится проверка записей в Базе Данных о вложениях запущена! Не прерывайте процесс!',
'SYNC_OK'                                    => 'Категория успешно синхронизирована.',
'THUMBNAIL_EXPLAIN'                          => 'Размеры миниатюр задаются в соответствующем разделе ACP (&laquo;Настройки вложений&raquo;).',
'UPLOAD_DENIED_ARTICLE'                      => 'Статьи с таким номером не существует.',
'UPLOADING_FILE_TO_ARTICLE'                  => 'Загрузка файла «%1$s» в статью',

//Log
'ACP_LIBRARY_LOGS_EXPLAIN'                   => 'Это список действий, выполненных с библиотекой. Вы можете сортировать список по имени пользователя, дате, IP-адресу или по действию. Вы можете удалить отдельные записи или очистить весь лог целиком.',
'LOG_CLEAR_KB'                               => '<strong>Очищены логи библиотеки</strong>',
'LOG_CATS_ADD'                               => '<strong>Создана категория</strong><br>» %s',
'LOG_CATS_DEL_ARTICLES'                      => '<strong>Удалена категория со статьями</strong><br>» %s',
'LOG_CATS_DEL_MOVE_POSTS_MOVE_CATS'          => '<strong>Удалена категория</strong> » %3$s, <strong>перемещены статьи в </strong> » %1$s <strong>и подкатегории в</strong> » %2$s',
'LOG_CATS_DEL_MOVE_POSTS'                    => '<strong>Удалена категория</strong> » %2$s<br><strong>и перемещены статьи в</strong> » %1$s',
'LOG_CATS_DEL_CAT'                           => '<strong>Удалена категория</strong><br>» %s',
'LOG_CATS_DEL_MOVE_POSTS_CATS'               => '<strong>Удалена категория</strong> » %2$s <strong><br>с подкатегориями, статьи перемещены в</strong> » %1$s',
'LOG_CATS_DEL_POSTS_MOVE_CATS'               => '<strong>Удалена категория</strong> » %2$s <strong>со статьями, подкатегории перемещены</strong> » %1$s',
'LOG_CATS_DEL_POSTS_CATS'                    => '<strong>Удалена категория со статьями и подкатегориями</strong><br>» %s',
'LOG_CATS_EDIT'                              => '<strong>Изменена информация о категории</strong><br>» %1$s',
'LOG_CATS_CAT_MOVED_TO'                      => '<strong>Категория</strong> » %1$s <strong>перемещена в</strong> %2$s',
'LOG_CATS_MOVE_UP'                            => '<strong>Категория</strong> » %1$s <strong>перемещена вверх, мимо</strong> » %2$s',
'LOG_CATS_MOVE_DOWN'                          => '<strong>Категория</strong> » %1$s <strong>перемещена вниз, мимо</strong> » %2$s',
'LOG_CATS_SYNC'                               => '<strong>Синхронизировано количество статей в категории</strong><br>» %s',
'LOG_KB_CONFIG_SEARCH'                       => '<strong>Изменены настройки поиска</strong>',
'LOG_LIBRARY_ADD_ARTICLE'                    => 'Добавлена статья &laquo;<strong>%1s</strong>&raquo; в категорию<br>» <strong>%2s</strong>',
'LOG_LIBRARY_DEL_ARTICLE'                    => 'Удалена статья &laquo;<strong>%1s</strong>&raquo; из категории<br>» <strong>%2s</strong>',
'LOG_LIBRARY_EDIT_ARTICLE'                   => 'Отредактирована статья &laquo;<strong>%1s</strong>&raquo; в категории<br>» <strong>%2s</strong>',
'LOG_LIBRARY_MOVED_ARTICLE'                  => 'Перемещена статья <strong>%1s</strong> из категории » <strong>%2s</strong><br>в категорию » <strong>%3s</strong>',
'LOG_LIBRARY_APPROVED_ARTICLE'               => 'Одобрена статья <strong>%1s</strong> из категории » <strong>%2s</strong><br>добавленная пользователем » <strong>%3s</strong>',
'LOG_LIBRARY_REJECTED_ARTICLE'               => 'Отклонена статья <strong>%1s</strong> из категории » <strong>%2s</strong><br>добавленная пользователем » <strong>%3s</strong>',
'LOG_LIBRARY_PERMISSION_DELETED'             => 'Удалён доступ пользователя/группы к категории <strong>%1s</strong><br>» %2s',
'LOG_LIBRARY_PERMISSION_ADD'                 => 'Добавлен или изменен доступ пользователя/группы к категории <strong>%1s</strong><br>» %2s',
'LOG_LIBRARY_CONFIG'                         => '<strong>Изменена конфигурация библиотеки</strong>',

'KB_TRACE_GROUP_NEVER_TOTAL_NO_LOCAL'  => 'Значение права группы для этой категории <strong>НИКОГДА</strong> становится новым результирующим, так как ранее не было задано (было задано <strong>НЕТ</strong>).',
'KB_TRACE_GROUP_NEVER_TOTAL_YES_LOCAL' => 'Значение права группы для этой категории <strong>НИКОГДА</strong> заменяет результирующее <strong>ДА</strong> на <strong>НИКОГДА</strong> для этого пользователя.',
'KB_TRACE_GROUP_NO_LOCAL'              => 'Значение права для этой группы в этой категории <strong>НЕТ</strong>, поэтому сохранено ранее заданное значение.',
'KB_TRACE_GROUP_YES_TOTAL_NEVER_LOCAL' => 'Значение права группы для этой категории <strong>ДА</strong>, но результирующее право <strong>НИКОГДА</strong> не может быть заменено.',
'KB_TRACE_GROUP_YES_TOTAL_NO_LOCAL'    => 'Значение права группы для этой категории <strong>ДА</strong> становится новым результирующим, так как ранее не было задано (было задано <strong>НЕТ</strong>).',
'KB_TRACE_GROUP_YES_TOTAL_YES_LOCAL'   => 'Значение права группы для этой категории <strong>ДА</strong>, результирующим правом также является <strong>ДА</strong>, поэтому сохранено ранее заданное значение.',

'KB_TRACE_USER_FOUNDER' => 'Пользователь является основателем конференции, поэтому значения всех прав всегда установлены в значение <strong>ДА</strong>.',
'KB_TRACE_USER_ADMIN'   => 'Пользователь является администратором конференции с правом <strong>Может управлять библиотекой</strong>, поэтому значения всех прав всегда установлены в значение <strong>ДА</strong>.',

'KB_TRACE_USER_KEPT_LOCAL'              => 'Значение права для этого пользователя в данной категории <strong>НЕТ</strong>, таким образом, сохранено ранее заданное результирующее значение.',
'KB_TRACE_USER_NEVER_TOTAL_NEVER_LOCAL' => 'Значение права для этого пользователя в данной категории <strong>НИКОГДА</strong>, результирующим правом также является <strong>НИКОГДА</strong>, поэтому изменения не производятся.',
'KB_TRACE_USER_NEVER_TOTAL_NO_LOCAL'    => 'Значение права для этого пользователя в данной категории <strong>НИКОГДА</strong> становится новым результирующим правом, так как ранее было задано <strong>НЕТ</strong>.',
'KB_TRACE_USER_NEVER_TOTAL_YES_LOCAL'   => 'Значение права для этого пользователя в данной категории <strong>НИКОГДА</strong> заменяет ранее заданное значение <strong>ДА</strong>.',
'KB_TRACE_USER_NO_TOTAL_NO_LOCAL'       => 'Значение права для этого пользователя в данной категории <strong>НЕТ</strong>, результирующим правом также является <strong>НЕТ</strong>, поэтому установлено значение по умолчанию <strong>НИКОГДА</strong>.',
'KB_TRACE_USER_YES_TOTAL_NEVER_LOCAL'   => 'Значение права для этого пользователя в данной категории <strong>ДА</strong>, но результирующе право <strong>НИКОГДА</strong> не может быть заменено.',
'KB_TRACE_USER_YES_TOTAL_NO_LOCAL'      => 'Значение права для этого пользователя в данной категории <strong>ДА</strong> становится новым результирующим правом, так как ранее было задано <strong>НЕТ</strong>.',
'KB_TRACE_USER_YES_TOTAL_YES_LOCAL'     => 'Значение права для этого пользователя в данной категории <strong>ДА</strong>, результирующим правом также является <strong>ДА</strong>, поэтому изменения не производятся.',

'KNOWLEDGE_BASE' => 'Библиотека',

	// Added for Phase 1-5 (custom fields, flat index) and the missing global-trace variants
	'KB_FIELDS_EXPLAIN'                       => 'Пользовательские поля позволяют помечать статьи дополнительной структурированной информацией, например, к каким версиям phpBB относится статья. Участники могут фильтровать статьи по полю при просмотре категории.',
	'KB_FIELD_ADD'                            => 'Добавить поле',
	'KB_FIELD_ADDED'                          => 'Поле было добавлено.',
	'KB_FIELD_EDIT_VALUES'                    => 'Изменить значения',
	'KB_FIELD_INVALID'                        => 'Пожалуйста, введите корректное имя, ключ и тип поля.',
	'KB_FIELD_KEY'                            => 'Ключ поля',
	'KB_FIELD_KEY_EXPLAIN'                    => 'Короткое уникальное техническое имя (только буквы, цифры и подчёркивания) - не отображается участникам.',
	'KB_FIELD_KEY_TAKEN'                      => 'Этот ключ поля уже используется.',
	'KB_FIELD_NAME'                           => 'Название поля',
	'KB_FIELD_NEW_VALUE'                      => 'Добавить новое значение',
	'KB_FIELD_NOT_FOUND'                      => 'Это поле не существует.',
	'KB_FIELD_TYPE'                           => 'Тип поля',
	'KB_FIELD_TYPE_MULTI_SELECT'              => 'Список с флажками',
	'KB_FIELD_UPDATED'                        => 'Настройки успешно обновлены.',
	'KB_FIELD_REQUIRED'                       => 'Обязательное',
	'KB_FIELD_REQUIRED_EXPLAIN'               => 'Авторы должны выбрать хотя бы одно значение этого поля при отправке статьи или перенаправления. Черновик можно сохранить и без значения. Поле без включённых значений не проверяется.',
	'KB_FIELD_MAKE_REQUIRED'                  => 'сделать обязательным',
	'KB_FIELD_MAKE_OPTIONAL'                  => 'сделать необязательным',
	'KB_FIELD_VALUES_EXPLAIN'                 => 'Добавляйте, включайте/отключайте или удаляйте значения, которые участники могут выбирать для этого поля. Отключение значения скрывает его из формы публикации, но сохраняет на статьях, где оно уже используется.',
	'KB_FIELD_VALUE_ENABLED'                  => 'Включено',
	'KB_FLAT_INDEX'                           => 'Плоский индекс статей',
	'KB_FLAT_INDEX_EXPLAIN'                   => 'При включении индекс статей отображает единый выпадающий список со всеми категориями вместо иерархического просмотра подкатегорий, а страница индекса по умолчанию показывает все статьи из всех категорий.',
	'KB_REQUIRE_PM'                           => 'Требовать личное сообщение автору',
	'KB_REQUIRE_PM_EXPLAIN'                   => 'Если включено, личное сообщение автору при одобрении/отклонении всегда доставляется, независимо от того, отключил ли автор его в своих настройках уведомлений. Если выключено (по умолчанию), это остаётся обычным опциональным способом уведомления, как Email или Jabber.',
	'KB_NO_FIELDS'                            => 'Пользовательские поля ещё не определены.',
	'KB_TEAMS'                                => 'Команды',
	'KB_TEAMS_EXPLAIN'                        => 'Выберите группы пользователей, которым можно назначать статьи на проверку. Участники отмеченной группы могут проверять статьи, назначенные этой группе, и публиковать статьи от имени группы вместо своего собственного.',
	'KB_CONTACT_GROUP'                         => 'Группа для связи',
	'KB_CONTACT_GROUP_EXPLAIN'                 => 'На странице управления своими статьями авторам предлагается отправить личное сообщение участнику этой группы, если у них есть вопросы по статьям; название группы ведёт на список её участников. Оставьте «Нет», чтобы не показывать это предложение.',
	'KB_DESCRIPTION_MAX_LENGTH'                => 'Максимальная длина описания',
	'KB_DESCRIPTION_MAX_LENGTH_EXPLAIN'        => 'Наибольшая длина описания статьи в символах при отправке статьи или перенаправления. Не может быть больше 255 — ширины столбца в базе данных.',
	'KB_TRACE_GROUP_NEVER_TOTAL_NO'           => 'Право этой группы установлено в значение <strong>НИКОГДА</strong>, которое становится новым итоговым значением, так как оно ещё не было задано (было <strong>НЕТ</strong>).',
	'KB_TRACE_GROUP_NEVER_TOTAL_YES'          => 'Право этой группы установлено в значение <strong>НИКОГДА</strong>, которое заменяет итоговое значение <strong>ДА</strong> на <strong>НИКОГДА</strong> для этого пользователя.',
	'KB_TRACE_GROUP_NO'                       => 'Право для этой группы — <strong>НЕТ</strong>, поэтому сохранено прежнее итоговое значение.',
	'KB_TRACE_GROUP_YES_TOTAL_NEVER'          => 'Право этой группы установлено в значение <strong>ДА</strong>, но итоговое значение <strong>НИКОГДА</strong> не может быть заменено.',
	'KB_TRACE_GROUP_YES_TOTAL_NO'             => 'Право этой группы установлено в значение <strong>ДА</strong>, которое становится новым итоговым значением, так как оно ещё не было задано (было <strong>НЕТ</strong>).',
	'KB_TRACE_GROUP_YES_TOTAL_YES'            => 'Право этой группы установлено в значение <strong>ДА</strong>, и итоговое значение уже установлено в <strong>ДА</strong>, поэтому итог сохранён.',
	'KB_TRACE_USER_KEPT'                      => 'Право пользователя — <strong>НЕТ</strong>, поэтому сохранено прежнее итоговое значение.',
	'KB_TRACE_USER_NEVER_TOTAL_NEVER'         => 'Право пользователя установлено в значение <strong>НИКОГДА</strong>, и итоговое значение установлено в <strong>НИКОГДА</strong>, поэтому ничего не меняется.',
	'KB_TRACE_USER_NEVER_TOTAL_NO'            => 'Право пользователя установлено в значение <strong>НИКОГДА</strong>, которое становится итоговым значением, так как оно было НЕТ.',
	'KB_TRACE_USER_NEVER_TOTAL_YES'           => 'Право пользователя установлено в значение <strong>НИКОГДА</strong> и заменяет предыдущее значение <strong>ДА</strong>.',
	'KB_TRACE_USER_NO_TOTAL_NO'               => 'Право пользователя — <strong>НЕТ</strong>, а итоговое значение было НЕТ, поэтому по умолчанию оно становится <strong>НИКОГДА</strong>.',
	'KB_TRACE_USER_YES_TOTAL_NEVER'           => 'Право пользователя установлено в значение <strong>ДА</strong>, но итоговое значение <strong>НИКОГДА</strong> не может быть заменено.',
	'KB_TRACE_USER_YES_TOTAL_NO'              => 'Право пользователя установлено в значение <strong>ДА</strong>, которое становится итоговым значением, так как оно было установлено в <strong>НЕТ</strong>.',
	'KB_TRACE_USER_YES_TOTAL_YES'             => 'Право пользователя установлено в значение <strong>ДА</strong>, и итоговое значение установлено в <strong>ДА</strong>, поэтому ничего не меняется.',
]);
