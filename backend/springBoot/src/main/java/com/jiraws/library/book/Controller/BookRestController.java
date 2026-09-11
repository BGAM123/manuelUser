package com.jiraws.library.book.Controller;

import com.jiraws.library.book.Entities.Exception.BookCreationException;
import com.jiraws.library.book.Services.BookService;
import com.jiraws.library.book.dto.BookDTO;
import jakarta.validation.Valid;
import org.apache.coyote.BadRequestException;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/book")
public class BookRestController {

    private  final BookService bookService;

    public BookRestController(BookService bookService){

        this.bookService = bookService;
    }
    @GetMapping

    public String getBook(@RequestParam String bookName, @RequestParam Integer bookPage ) {


        //return bookService.createBook(bookName, bookPage) ;
        return "ok get";

    }
    @PostMapping
    public String postBook( @Valid @RequestBody BookDTO.PostInput input) throws BookCreationException {
        return bookService.createBook(input.getBookName(), input.getBookPage()) ;
    }
}
