#Persistent
SetTitleMatchMode, 2 ; 设置窗口匹配模式为标题部分匹配

; 全局变量，用于控制热键是否启用
isHotkeysEnabled := true

; 定义热键：F12（切换热键开关）
F12::
{
    isHotkeysEnabled := !isHotkeysEnabled ; 切换热键开关状态
    if (isHotkeysEnabled)
    {
        MsgBox, 热键已启用。
    }
    else
    {
        MsgBox, 热键已禁用。
    }
    return
}

; 定义热键：F6 一键止赢
F6::
{
    if (!isHotkeysEnabled)
        return

    ; 获取当前活动窗口的标题
    WinGetActiveTitle, activeTitle

    ; 判断当前活动窗口是否是cTrader
    if (InStr(activeTitle, "cTrader") = 0) {
        ; 如果当前窗口不是cTrader，则激活cTrader窗口
        ; 确保窗口标题包含cTrader
        WinActivate, cTrader
        ; 设置cTrader窗口位置和大小为 80, 80, 2600, 1300
        WinMove, cTrader,, 80, 80, 2600, 1300
        ; 模拟点击位置 301, 500（空格键新位置）
        Click, 301, 500
    } else {
        ; 如果cTrader窗口已经在最前面并且窗口位置和大小已经是 80, 80, 2600, 1300
        ; 判断窗口位置和大小是否符合要求
        WinGetPos, X, Y, Width, Height, cTrader
        if (X = 80 and Y = 80 and Width = 2600 and Height = 1300) {
            ; 如果窗口位置和大小是正确的，直接模拟点击位置 301, 500（空格键新位置）
            Click, 301, 500
        }
    }
    return
}

; 定义热键：F5键   关闭所有
F5::
{
    if (!isHotkeysEnabled)
        return

    ; 获取当前活动窗口的标题
    WinGetActiveTitle, activeTitle

    ; 判断当前活动窗口是否是cTrader
    if (InStr(activeTitle, "cTrader") = 0) {
        ; 如果当前窗口不是cTrader，则激活cTrader窗口
        ; 确保窗口标题包含cTrader
        WinActivate, cTrader
        ; 设置cTrader窗口位置和大小为 80, 80, 2600, 1300
        WinMove, cTrader,, 80, 80, 2600, 1300
        ; 模拟点击位置 580, 500（Esc键点击位置）
        Click, 580, 500
    } else {
        ; 如果cTrader窗口已经在最前面并且窗口位置和大小已经是 80, 80, 2600, 1300
        ; 判断窗口位置和大小是否符合要求
        WinGetPos, X, Y, Width, Height, cTrader
        if (X = 80 and Y = 80 and Width = 2600 and Height = 1300) {
            ; 如果窗口位置和大小是正确的，直接模拟点击位置 580, 500（Esc键点击位置）
            Click, 580, 500
        }
    }
    return
}

; 定义热键：F1键（买入 0.1手）
F1::
{
    if (!isHotkeysEnabled)
        return

    ; 获取当前活动窗口的标题
    WinGetActiveTitle, activeTitle

    ; 判断当前活动窗口是否是cTrader
    if (InStr(activeTitle, "cTrader") = 0) {
        ; 如果当前窗口不是cTrader，则激活cTrader窗口
        ; 确保窗口标题包含cTrader
        WinActivate, cTrader
        ; 设置cTrader窗口位置和大小为 80, 80, 2600, 1300
        WinMove, cTrader,, 80, 80, 2600, 1300
        ; 模拟点击位置 300, 414（买入 0.1手）
        Click, 296, 414
    } else {
        ; 如果cTrader窗口已经在最前面并且窗口位置和大小已经是 80, 80, 2600, 1300
        ; 判断窗口位置和大小是否符合要求
        WinGetPos, X, Y, Width, Height, cTrader
        if (X = 80 and Y = 80 and Width = 2600 and Height = 1300) {
            ; 如果窗口位置和大小是正确的，直接模拟点击位置 300, 414（买入 0.1手）
            Click, 296, 414
        }
    }
    return
}

; 定义热键：F2键（卖出 0.1手）
F2::
{
    if (!isHotkeysEnabled)
        return

    ; 获取当前活动窗口的标题
    WinGetActiveTitle, activeTitle

    ; 判断当前活动窗口是否是cTrader
    if (InStr(activeTitle, "cTrader") = 0) {
        ; 如果当前窗口不是cTrader，则激活cTrader窗口
        ; 确保窗口标题包含cTrader
        WinActivate, cTrader
        ; 设置cTrader窗口位置和大小为 80, 80, 2600, 1300
        WinMove, cTrader,, 80, 80, 2600, 1300
        ; 模拟点击位置 640, 414（卖出 0.1手）
        Click, 640, 414
    } else {
        ; 如果cTrader窗口已经在最前面并且窗口位置和大小已经是 80, 80, 2600, 1300
        ; 判断窗口位置和大小是否符合要求
        WinGetPos, X, Y, Width, Height, cTrader
        if (X = 80 and Y = 80 and Width = 2600 and Height = 1300) {
            ; 如果窗口位置和大小是正确的，直接模拟点击位置 640, 414（卖出 0.1手）
            Click, 640, 414
        }
    }
    return
}

; F3 按键逻辑 一键平仓
F3::
{
    if (!isHotkeysEnabled)
        return

    ; 检测是否为 MT5 窗口活动
    IfWinNotActive, ahk_exe terminal64.exe
    {
        ; 如果不是活动窗口，则先激活 MT5 窗口
        WinActivate, ahk_exe terminal64.exe
        ; 确保窗口激活完成后再执行后续操作
        WinWaitActive, ahk_exe terminal64.exe,, 2
        if !WinActive("ahk_exe terminal64.exe")
        {
            MsgBox, Metatrader5 无法激活，请检查是否运行。
            return
        }
    }
    ; 激活后发送快捷键 Ctrl+1
    Send, ^1
    return
}

; F4 按键逻辑 一键止盈
F4::
{
    if (!isHotkeysEnabled)
        return

    ; 检测是否为 MT5 窗口活动
    IfWinNotActive, ahk_exe terminal64.exe
    {
        ; 如果不是活动窗口，则先激活 MT5 窗口
        WinActivate, ahk_exe terminal64.exe
        ; 确保窗口激活完成后再执行后续操作
        WinWaitActive, ahk_exe terminal64.exe,, 2
        if !WinActive("ahk_exe terminal64.exe")
        {
            MsgBox, Metatrader5 无法激活，请检查是否运行。
            return
        }
    }
    ; 激活后发送快捷键 Ctrl+2
    Send, ^2
    return
}
