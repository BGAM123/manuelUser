package com.jiraws.library.book.Services;

import ch.qos.logback.core.util.StringUtil;
import com.jiraws.library.book.Entities.Exception.BookCreationException;
import com.jiraws.library.book.Repositories.BookRepository;
import com.jiraws.library.book.Entities.BookEntity;
import io.micrometer.common.util.StringUtils;
import org.apache.coyote.BadRequestException;
import org.springframework.stereotype.Service;

@Service
public class BookService {

    private  final BookRepository bookRepository;

    public BookService(BookRepository bookRepository) {
        this.bookRepository = bookRepository;
    }

    public String createBook(String bookName, Integer bookPage) throws BookCreationException {
        if(bookName == null || StringUtils.isBlank(bookName)){
            //return "le bookName ne peut pas etre vide ou null";
            throw new BookCreationException("Le nom du livre ne peut pas etre null");

        }
         if(bookPage == null || bookPage <= 0){
            //return "le bookPage ne peut pas etre vide ou inferieur ou egale a 0";
            throw new BookCreationException("le bookPage ne peut pas etre vide ou inferieur ou egale a 0");
        }
        BookEntity existingBook = bookRepository.findByNameAndPages(bookName, bookPage);

        if(existingBook == null){
            BookEntity newBook = BookEntity.builder()
                    .name(bookName)
                    .pages(bookPage)
                    .build();
            System.out.println(bookName);
            System.out.println(bookPage);
            bookRepository.save(newBook);
            return "Le livre a ete creer";
        }
        else {
            throw new BookCreationException("Le livre existe deja");
        }
    }

}
